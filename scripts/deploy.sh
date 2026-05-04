#!/bin/bash
set -eo pipefail

PROJECT_DIR="/home/bansallawyercrm"
APP_USER="ubuntu"
WEB_USER="www-data"
PHP_FPM="php8.2-fpm"
SOURCE_DIR="/tmp/bansallawyercrm-build"

# Resolve PHP binary. ondrej/php PPA installs as php8.2; fallback to php.
if command -v php8.2 &>/dev/null; then PHP_BIN="php8.2"; else PHP_BIN="php"; fi

# Resolve Composer binary.
COMPOSER_BIN="/usr/local/bin/composer"
[ ! -x "$COMPOSER_BIN" ] && COMPOSER_BIN="$(command -v composer)"

# Run as APP_USER with cwd = PROJECT_DIR. sudo often resets home/cwd; Laravel needs artisan in cwd.
app_run() {
  sudo -H -u "$APP_USER" bash -lc "cd \"$PROJECT_DIR\" && $1"
}

# CodeBuild artifact excludes bootstrap/cache/**/* — rsync may leave no cache dir. Laravel needs
# bootstrap/cache + storage writable before any artisan (maintenance, composer hooks).
ensure_laravel_writable_dirs() {
  mkdir -p \
    "$PROJECT_DIR/bootstrap/cache" \
    "$PROJECT_DIR/storage/framework/sessions" \
    "$PROJECT_DIR/storage/framework/views" \
    "$PROJECT_DIR/storage/framework/cache/data" \
    "$PROJECT_DIR/storage/logs"
  chown -R "$APP_USER:$WEB_USER" "$PROJECT_DIR/bootstrap/cache" "$PROJECT_DIR/storage"
  chmod -R 775 "$PROJECT_DIR/bootstrap/cache" "$PROJECT_DIR/storage"
}

echo "======================================"
echo "Deployment starting"
echo "PHP : $($PHP_BIN -r 'echo PHP_VERSION;')"
echo "User: $APP_USER  Dir: $PROJECT_DIR"
echo "======================================"

# ── Pre-flight: APP_KEY must be present in the server's .env ────────
# The .env is excluded from rsync and must be managed manually.
# A missing APP_KEY causes every request to throw MissingAppKeyException.
ENV_FILE="$PROJECT_DIR/.env"
if [ -f "$ENV_FILE" ]; then
    APP_KEY_VALUE=$(grep -E '^APP_KEY=' "$ENV_FILE" | cut -d'=' -f2-)
    if [ -z "$APP_KEY_VALUE" ] || [ "$APP_KEY_VALUE" = '""' ] || [ "$APP_KEY_VALUE" = "''" ]; then
        echo "FATAL: APP_KEY is not set in $ENV_FILE."
        echo "  Run:  php artisan key:generate --force"
        echo "  Then re-run this deployment."
        exit 1
    fi
else
    echo "FATAL: $ENV_FILE does not exist on this server."
    echo "  Copy .env.example to .env, fill in all values, then re-run."
    exit 1
fi

mkdir -p "$PROJECT_DIR"
cd "$PROJECT_DIR"
ensure_laravel_writable_dirs

# ── [1/11] Maintenance mode ─────────────────────────────────────────
# /up stays HTTP 200 during maintenance (AppServiceProvider + routes/web.php)
# so the ALB target group health check keeps passing throughout the deploy.
echo "[1/11] Enabling maintenance mode..."
app_run "$PHP_BIN artisan down --retry=60 || true"

# ── [2/11] Sync build artifact → project directory ──────────────────
echo "[2/11] Syncing source to project..."
rsync -a --delete \
  --exclude='.env' \
  --exclude='storage/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='bootstrap/cache/' \
  "$SOURCE_DIR/" "$PROJECT_DIR/"

# ── [3/11] Ownership ────────────────────────────────────────────────
echo "[3/11] Fixing ownership..."
chown -R "$APP_USER:$WEB_USER" "$PROJECT_DIR"
ensure_laravel_writable_dirs

cd "$PROJECT_DIR"

# ── [4/11] Composer ─────────────────────────────────────────────────
echo "[4/11] Installing Composer dependencies..."
app_run "$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --quiet"

# ── [5/11] Python venv ──────────────────────────────────────────────
# Written to a temp file to avoid quoting/escaping issues with nested heredocs.
echo "[5/11] Setting up Python virtual environment..."
cat > /tmp/_setup_venv.sh << 'VENVEOF'
#!/bin/bash
set -e
PROJ_DIR="$1"
cd "$PROJ_DIR/python_services"

SYS_PY=$(python3 --version 2>&1 | awk '{print $2}')
VENV_PY=""
if [ -f venv/pyvenv.cfg ]; then
    VENV_PY=$(grep "^version = " venv/pyvenv.cfg 2>/dev/null | awk '{print $3}')
fi

if [ ! -d venv ] || [ "$SYS_PY" != "$VENV_PY" ]; then
    echo "  Recreating venv (system Python $SYS_PY, previous: ${VENV_PY:-none})..."
    rm -rf venv
    python3 -m venv venv
fi

echo "  Installing Python dependencies (Python $SYS_PY)..."
./venv/bin/pip install --upgrade pip --quiet
./venv/bin/pip install -r requirements.txt --quiet
echo "  Python venv ready."
VENVEOF
sudo -u "$APP_USER" bash /tmp/_setup_venv.sh "$PROJECT_DIR"
rm -f /tmp/_setup_venv.sh

# ── [6/11] Clear config cache so .env changes apply before migrate ──
echo "[6/11] Clearing config cache..."
app_run "$PHP_BIN artisan config:clear"

# ── [7/11] Migrations ───────────────────────────────────────────────
echo "[7/11] Running migrations..."

# Laravel queue / email workers and the Python migration service each hold PostgreSQL
# connections. PHP-FPM workers often hold one connection per child — a reload does not
# always free slots fast enough. Stopping PHP-FPM during migrate drops those connections;
# artisan still runs via CLI ($PHP_BIN). We start PHP-FPM again before cache rebuild / step 11.
# If the ALB probes /up via PHP during this window, checks may briefly fail — keep migrate + retries as short as practical.
start_db_consuming_services() {
  for SVC in bansallaw-queue bansallaw-email migration-python-services; do
    if systemctl is-enabled --quiet "$SVC" 2>/dev/null; then
      if ! systemctl start "$SVC"; then
        echo "  WARN: systemctl start $SVC failed — check: systemctl status $SVC"
      fi
    fi
  done
}

PHP_FPM_STOPPED_FOR_MIGRATE=0
start_php_fpm_if_we_stopped_it() {
  if [ "${PHP_FPM_STOPPED_FOR_MIGRATE:-0}" = 1 ]; then
    echo "  Starting $PHP_FPM (restore web / health checks)..."
    if systemctl start "$PHP_FPM"; then
      PHP_FPM_STOPPED_FOR_MIGRATE=0
    else
      echo "  WARN: systemctl start $PHP_FPM failed — check: systemctl status $PHP_FPM"
    fi
  fi
}

echo "  Pausing DB-heavy workers before migrate..."
app_run "$PHP_BIN artisan queue:restart || true"
sleep 2

for SVC in bansallaw-queue bansallaw-email migration-python-services; do
  if systemctl is-enabled --quiet "$SVC" 2>/dev/null && systemctl is-active --quiet "$SVC" 2>/dev/null; then
    systemctl stop "$SVC" || echo "  WARN: systemctl stop $SVC failed"
  fi
done

if systemctl is-active --quiet "$PHP_FPM" 2>/dev/null; then
  echo "  Stopping $PHP_FPM to release PostgreSQL connections (CLI still runs migrate)..."
  systemctl stop "$PHP_FPM" || echo "  WARN: systemctl stop $PHP_FPM failed"
  PHP_FPM_STOPPED_FOR_MIGRATE=1
fi
sleep 5

MIGRATE_ATTEMPTS=0
MIGRATE_MAX=5
MIGRATE_DELAY=4
while [ "$MIGRATE_ATTEMPTS" -lt "$MIGRATE_MAX" ]; do
  MIGRATE_ATTEMPTS=$((MIGRATE_ATTEMPTS + 1))
  # Disable persistent PDO for this process only (if .env enables it, it can multiply connections).
  if app_run "DB_PERSISTENT=false $PHP_BIN artisan migrate --force"; then
    break
  fi
  if [ "$MIGRATE_ATTEMPTS" -ge "$MIGRATE_MAX" ]; then
    echo "FATAL: migrate failed after $MIGRATE_MAX attempts (check PostgreSQL max_connections and connection usage)."
    start_db_consuming_services
    start_php_fpm_if_we_stopped_it
    exit 1
  fi
  echo "  Migrate attempt $MIGRATE_ATTEMPTS failed; waiting ${MIGRATE_DELAY}s before retry..."
  sleep "$MIGRATE_DELAY"
  MIGRATE_DELAY=$((MIGRATE_DELAY * 2))
done

start_php_fpm_if_we_stopped_it
# app_run "$PHP_BIN artisan import:reference-master-data"

# ── [8/11] Required storage / cache directories ─────────────────────
echo "[8/11] Ensuring required directories..."
ensure_laravel_writable_dirs

# ── [9/11] Rebuild framework caches ────────────────────────────────
echo "[9/11] Rebuilding caches..."
app_run "$PHP_BIN artisan view:clear"
app_run "$PHP_BIN artisan config:cache"
app_run "$PHP_BIN artisan route:cache"
app_run "$PHP_BIN artisan event:cache"
app_run "$PHP_BIN artisan storage:link --force"

# ── [10/11] Permissions ─────────────────────────────────────────────
echo "[10/11] Fixing permissions..."
chown -R "$APP_USER:$WEB_USER" "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# ── [11/11] Service restarts ────────────────────────────────────────
echo "[11/11] Restarting services..."

# Tell queue workers to finish their current job cleanly before the hard restart.
app_run "$PHP_BIN artisan queue:restart || true"

RESTART_ERRORS=0
for SVC in $PHP_FPM bansallaw-queue bansallaw-email migration-python-services; do
    if systemctl is-enabled --quiet "$SVC" 2>/dev/null; then
        if systemctl restart "$SVC"; then
            echo "  OK   : $SVC restarted"
        else
            echo "  WARN : $SVC failed to restart — check: systemctl status $SVC"
            RESTART_ERRORS=$((RESTART_ERRORS + 1))
        fi
    else
        echo "  SKIP : $SVC is not enabled"
    fi
done

# ValidateService curls 127.0.0.1 — if the unit is enabled but never started, HTTP 000 results.
echo "Ensuring web server is running..."
if systemctl is-enabled --quiet apache2 2>/dev/null && ! systemctl is-active --quiet apache2 2>/dev/null; then
    echo "  Starting apache2 (enabled but not active)..."
    systemctl start apache2 || echo "  WARN: systemctl start apache2 failed — check: systemctl status apache2"
fi
if systemctl is-enabled --quiet nginx 2>/dev/null && ! systemctl is-active --quiet nginx 2>/dev/null; then
    echo "  Starting nginx (enabled but not active)..."
    systemctl start nginx || echo "  WARN: systemctl start nginx failed — check: systemctl status nginx"
fi

echo "Reloading web server..."
if systemctl is-active --quiet apache2; then
    systemctl reload apache2
elif systemctl is-active --quiet nginx; then
    systemctl reload nginx
else
    echo "  WARN : no active web server (apache2 / nginx) found"
fi

echo "Disabling maintenance mode..."
app_run "$PHP_BIN artisan up"

echo "======================================"
if [ "$RESTART_ERRORS" -gt 0 ]; then
    echo "Deployment DONE with $RESTART_ERRORS service restart warning(s)."
else
    echo "Deployment complete."
fi
echo "======================================"
