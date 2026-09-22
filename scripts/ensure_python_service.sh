#!/bin/bash
# Keep the CRM Python email/PDF microservice listening on 127.0.0.1:5002.
#
# Production keepalive & auto-starter for .msg/.eml email processing.
# Cron (every 5 minutes), e.g. on legal.bansalcrm.com:
#   */5 * * * * /home/legalban/public_html/scripts/ensure_python_service.sh >> /home/legalban/public_html/storage/logs/python-ensure.log 2>&1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PYTHON_DIR="$PROJECT_DIR/python_services"
PORT="${SERVICE_PORT:-5002}"
HEALTH_URL="http://127.0.0.1:${PORT}/health"
SERVICE_NAME="migration-python-services"

# Check if already healthy
if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
  exit 0
fi

echo "$(date -Is) Python service unhealthy on :${PORT} — attempting restart"

# Try systemctl first if systemd service is configured
if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${SERVICE_NAME}.service" 2>/dev/null | grep -q "${SERVICE_NAME}"; then
  systemctl restart "$SERVICE_NAME" 2>/dev/null || systemctl start "$SERVICE_NAME" 2>/dev/null || true
  sleep 2
  if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
    echo "$(date -Is) Recovered via systemctl"
    exit 0
  fi
fi

cd "$PYTHON_DIR"
mkdir -p logs temp

# Resolve best Python interpreter
PY_BIN=""
for candidate in \
  "$PYTHON_DIR/venv/bin/python" \
  "$PYTHON_DIR/venv/bin/python3" \
  python3.13 \
  python3.12 \
  python3.11 \
  python3.10 \
  python3.9 \
  python3 \
  /usr/local/bin/python3.13 \
  /usr/local/bin/python3 \
  /opt/cpanel/ea-python311/root/usr/bin/python3 \
  /opt/cpanel/ea-python310/root/usr/bin/python3 \
  /opt/cpanel/ea-python39/root/usr/bin/python3 \
  /opt/alt/python311/bin/python3 \
  /opt/alt/python310/bin/python3 \
  /opt/alt/python39/bin/python3
do
  case "$candidate" in
    /*|*/*)
      if [ -x "$candidate" ]; then
        PY_BIN="$candidate"
        break
      fi
      ;;
    *)
      if command -v "$candidate" >/dev/null 2>&1; then
        PY_BIN="$(command -v "$candidate")"
        break
      fi
      ;;
  esac
done

if [ -z "$PY_BIN" ]; then
  echo "$(date -Is) FAILED: no suitable Python 3.9+ binary found"
  exit 1
fi

# Auto-create venv if missing and install dependencies
if [ ! -d "$PYTHON_DIR/venv" ] || [ ! -x "$PYTHON_DIR/venv/bin/python" ]; then
  echo "$(date -Is) Setting up venv with $PY_BIN..."
  "$PY_BIN" -m venv "$PYTHON_DIR/venv" 2>/dev/null || true
  if [ -x "$PYTHON_DIR/venv/bin/pip" ]; then
    "$PYTHON_DIR/venv/bin/pip" install --upgrade pip --quiet 2>/dev/null || true
    "$PYTHON_DIR/venv/bin/pip" install -r "$PYTHON_DIR/requirements.txt" --quiet 2>/dev/null || true
  fi
fi

if [ -x "$PYTHON_DIR/venv/bin/python" ]; then
  PY_BIN="$PYTHON_DIR/venv/bin/python"
fi

# Clear any dead / unresponsive process holding the port
if command -v lsof >/dev/null 2>&1 && lsof -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "$(date -Is) Port $PORT held by unresponsive process — terminating stale listener"
  PID=$(lsof -tiTCP:"$PORT" -sTCP:LISTEN 2>/dev/null || true)
  if [ -n "$PID" ]; then
    kill -9 $PID 2>/dev/null || true
    sleep 1
  fi
elif command -v fuser >/dev/null 2>&1; then
  fuser -k "${PORT}/tcp" 2>/dev/null || true
  sleep 1
fi

# Launch background service with nohup
nohup "$PY_BIN" main.py --host 127.0.0.1 --port "$PORT" >> logs/service.log 2>&1 &
sleep 2

# Verify health check with retries
for attempt in 1 2 3 4 5; do
  if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
    echo "$(date -Is) Recovered via nohup ($PY_BIN) on port $PORT"
    exit 0
  fi
  sleep 1
done

echo "$(date -Is) FAILED to start Python service on port $PORT — see $PYTHON_DIR/logs/service.log"
exit 1
