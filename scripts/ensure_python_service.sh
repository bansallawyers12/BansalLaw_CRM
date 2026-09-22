#!/bin/bash
# Keep the CRM Python email/PDF microservice listening on 127.0.0.1:5002.
# Intended for cron every 5 minutes on production:
#   */5 * * * * /home/legalban/public_html/scripts/ensure_python_service.sh >> /home/legalban/public_html/storage/logs/python-ensure.log 2>&1
#
# Adjust PROJECT_DIR below if the app lives elsewhere (e.g. /home/bansallawyercrm).

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

# Legacy doc deployments sometimes bind 5000 — accept that as healthy too.
if curl -sf --max-time 3 --connect-timeout 2 "http://127.0.0.1:5000/health" >/dev/null 2>&1; then
  exit 0
fi

echo "$(date -Is) Python service unhealthy — attempting restart"

if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${SERVICE_NAME}.service" 2>/dev/null | grep -q "${SERVICE_NAME}"; then
  systemctl restart "$SERVICE_NAME" || systemctl start "$SERVICE_NAME" || true
  sleep 2
  if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1 \
      || curl -sf --max-time 3 --connect-timeout 2 "http://127.0.0.1:5000/health" >/dev/null 2>&1; then
    echo "$(date -Is) Recovered via systemctl"
    exit 0
  fi
fi

cd "$PYTHON_DIR"
mkdir -p logs temp

PY_BIN="python3"
if [ -x "$PYTHON_DIR/venv/bin/python" ]; then
  PY_BIN="$PYTHON_DIR/venv/bin/python"
fi

# Avoid duplicate nohup processes if a previous start is still binding.
if command -v lsof >/dev/null 2>&1 && lsof -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "$(date -Is) Port $PORT already in use but /health failed — check logs"
  exit 1
fi

nohup "$PY_BIN" main.py --host 127.0.0.1 --port "$PORT" >> logs/service.log 2>&1 &
sleep 3

if curl -sf --max-time 3 --connect-timeout 2 "$HEALTH_URL" >/dev/null 2>&1; then
  echo "$(date -Is) Recovered via nohup ($PY_BIN)"
  exit 0
fi

echo "$(date -Is) FAILED to start Python service on port $PORT"
exit 1
