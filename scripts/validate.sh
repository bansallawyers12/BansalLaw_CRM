#!/bin/bash
set -e

# Probe the /up health endpoint on the local web server.
# The route returns HTTP 200 even during maintenance mode (AppServiceProvider).
# CodeDeploy calls this hook after AfterInstall; a non-zero exit fails the deployment.
#
# HTTP 000 from curl = no TCP/HTTP response (wrong port, web server down, or HTTPS vs HTTP mismatch).
# We try VALIDATE_HEALTH_URL, then localhost URL derived from APP_USER .env, then common fallbacks.

PROJECT_DIR="/home/bansallawyercrm"
ENV_FILE="$PROJECT_DIR/.env"
MAX_WAIT="${VALIDATE_MAX_WAIT:-100}"
INTERVAL=5
ELAPSED=0

curl_health() {
  local url="$1"
  local extra=()
  case "$url" in
    https://*) extra=(-k) ;;
  esac
  # shellcheck disable=SC2086
  curl -s "${extra[@]}" --max-time 5 --connect-timeout 3 \
    -w "\n__HTTP_CODE__:%{http_code}" "$url" 2>/dev/null || echo -e "\n__HTTP_CODE__:000"
}

build_health_urls() {
  declare -n out_array=$1
  out_array=()

  if [ -n "${VALIDATE_HEALTH_URL:-}" ]; then
    out_array+=("$VALIDATE_HEALTH_URL")
  fi

  if [ -f "$ENV_FILE" ]; then
    APP_URL_LINE=$(grep -E '^APP_URL=' "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '\r' | tr -d '"' | tr -d "'")
    if [ -n "$APP_URL_LINE" ] && command -v python3 &>/dev/null; then
      DERIVED=$(python3 -c "
import urllib.parse, sys
raw = sys.argv[1].strip()
if not raw:
    sys.exit(0)
u = urllib.parse.urlparse(raw)
scheme = (u.scheme or 'http').lower()
if scheme not in ('http', 'https'):
    scheme = 'http'
port = u.port
if port is None:
    port = 443 if scheme == 'https' else 80
host = '127.0.0.1'
if scheme == 'https':
    print(f'https://{host}/up' if port == 443 else f'https://{host}:{port}/up')
else:
    print(f'http://{host}/up' if port == 80 else f'http://{host}:{port}/up')
" "$APP_URL_LINE" 2>/dev/null) || true
      if [ -n "$DERIVED" ]; then
        out_array+=("$DERIVED")
      fi
    fi
  fi

  out_array+=(
    "http://127.0.0.1/up"
    "http://127.0.0.1:8080/up"
    "http://127.0.0.1:8000/up"
    "https://127.0.0.1/up"
  )
}

HEALTH_URLS=()
build_health_urls HEALTH_URLS

echo "ValidateService: waiting for /up (HTTP 200), max ${MAX_WAIT}s..."
echo "  Try order: ${HEALTH_URLS[*]}"

while [ "$ELAPSED" -lt "$MAX_WAIT" ]; do
  for url in "${HEALTH_URLS[@]}"; do
    RESPONSE_BODY=$(curl_health "$url")
    HTTP_CODE=$(echo "$RESPONSE_BODY" | grep -oE '__HTTP_CODE__:[0-9]+' | tail -1 | cut -d: -f2)
    [ -z "$HTTP_CODE" ] && HTTP_CODE=000

    if [ "$ELAPSED" -eq 0 ]; then
      echo "    $url -> HTTP $HTTP_CODE"
    fi

    if [ "$HTTP_CODE" = "200" ]; then
      echo "Health check PASSED (HTTP 200) via $url after ${ELAPSED}s."
      exit 0
    fi
  done

  if [ "$ELAPSED" -eq 0 ]; then
    echo "  First pass: no HTTP 200 yet; retrying every ${INTERVAL}s..."
  else
    echo "  No candidate returned HTTP 200 after ${ELAPSED}s -- retrying in ${INTERVAL}s..."
  fi
  sleep "$INTERVAL"
  ELAPSED=$((ELAPSED + INTERVAL))
done

echo "ERROR: /up did not return HTTP 200 within ${MAX_WAIT}s. Deployment failed."
echo "  Hint: ensure apache2 or nginx is active and listening (deploy.sh starts them if enabled)."
exit 1
