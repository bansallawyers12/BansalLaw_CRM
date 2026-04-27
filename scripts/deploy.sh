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

echo "======================================"
echo "Deployment starting"
echo "PHP : $($PHP_BIN -r 'echo PHP_VERSION;')"
echo "User: $APP_USER  Dir: $PROJECT_DIR"
echo "======================================"

mkdir -p "$PROJECT_DIR"
cd "$PROJECT_DIR"

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
  "$SOURCE_DIR/" "$PROJECT_DIR/"

# ── [3/11] Ownership ────────────────────────────────────────────────
echo "[3/11] Fixing ownership..."
chown -R $APP_USER:$WEB_USER "$PROJECT_DIR"

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
sudo -u $APP_USER bash /tmp/_setup_venv.sh "$PROJECT_DIR"
rm -f /tmp/_setup_venv.sh

# ── [6/11] Clear config cache so .env changes apply before migrate ──
echo "[6/11] Clearing config cache..."
app_run "$PHP_BIN artisan config:clear"

# ── [7/11] Migrations ───────────────────────────────────────────────
echo "[7/11] Running migrations..."
app_run "$PHP_BIN artisan migrate --force"
# app_run "$PHP_BIN artisan import:reference-master-data"

# ── [8/11] Required storage / cache directories ─────────────────────
echo "[8/11] Ensuring required directories..."
sudo -u $APP_USER mkdir -p \
  "$PROJECT_DIR/storage/framework/sessions" \
  "$PROJECT_DIR/storage/framework/views" \
  "$PROJECT_DIR/storage/framework/cache/data" \
  "$PROJECT_DIR/bootstrap/cache"

# ── [9/11] Rebuild framework caches ────────────────────────────────
echo "[9/11] Rebuilding caches..."
app_run "$PHP_BIN artisan view:clear"
app_run "$PHP_BIN artisan config:cache"
app_run "$PHP_BIN artisan route:cache"
app_run "$PHP_BIN artisan event:cache"
app_run "$PHP_BIN artisan storage:link --force"

# ── [10/11] Permissions ─────────────────────────────────────────────
echo "[10/11] Fixing permissions..."
chown -R $APP_USER:$WEB_USER "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
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
