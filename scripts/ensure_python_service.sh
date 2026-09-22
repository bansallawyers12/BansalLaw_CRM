#!/bin/bash
# Keep the CRM Python email/PDF microservice listening on 127.0.0.1:5002.
#
# This is the supported production fix for .msg/.eml upload "connection refused":
# Laravel expects the existing HTTP service (same as before), not a CLI workaround.
#
# Cron (every 5 minutes), e.g. on legal.bansalcrm.com:
#   */5 * * * * /home/legalban/public_html/scripts/ensure_python_service.sh >> /home/legalban/public_html/storage/logs/python-ensure.log 2>&1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PYTHON_DIR="$PROJECT_DIR/python_services"
PORT="${SERVICE_PORT:-5002}"
HEALTH_URL="http://127.0.0.1:${PORT}/health"
SERVICE_NAME="migration-python-services"

if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
  exit 0
fi

echo "$(date -Is) Python service unhealthy on :${PORT} — attempting restart"

if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${SERVICE_NAME}.service" 2>/dev/null | grep -q "${SERVICE_NAME}"; then
  systemctl restart "$SERVICE_NAME" || systemctl start "$SERVICE_NAME" || true
  sleep 2
  if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
    echo "$(date -Is) Recovered via systemctl"
    exit 0
  fi
fi

cd "$PYTHON_DIR"
mkdir -p logs temp

# Prefer the same interpreter as start_services.sh (python3.13), then venv, then python3.
PY_BIN=""
for candidate in \
  "$PYTHON_DIR/venv/bin/python" \
  "$PYTHON_DIR/venv/bin/python3" \
  python3.13 \
  python3.12 \
  python3.11 \
  python3
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
  echo "$(date -Is) FAILED: no python3.13/python3 found"
  exit 1
fi

if command -v lsof >/dev/null 2>&1 && lsof -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "$(date -Is) Port $PORT already in use but /health failed — check $PYTHON_DIR/logs/service.log"
  exit 1
fi

nohup "$PY_BIN" main.py --host 127.0.0.1 --port "$PORT" >> logs/service.log 2>&1 &
sleep 3

if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
  echo "$(date -Is) Recovered via nohup ($PY_BIN)"
  exit 0
fi

echo "$(date -Is) FAILED to start Python service on port $PORT — see $PYTHON_DIR/logs/service.log"
exit 1
