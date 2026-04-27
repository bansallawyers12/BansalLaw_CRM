#!/bin/bash
set -e

# Probe the /up health endpoint on the local web server.
# The route returns HTTP 200 even during maintenance mode (AppServiceProvider).
# CodeDeploy calls this hook after AfterInstall; a non-zero exit fails the deployment.
APP_URL="http://127.0.0.1"
MAX_WAIT=90
INTERVAL=5
ELAPSED=0

echo "ValidateService: waiting for $APP_URL/up to return HTTP 200 (max ${MAX_WAIT}s)..."
while [ "$ELAPSED" -lt "$MAX_WAIT" ]; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
        --max-time 5 --connect-timeout 3 "$APP_URL/up" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        echo "Health check PASSED (HTTP 200) after ${ELAPSED}s."
        exit 0
    fi
    echo "  HTTP $HTTP_CODE after ${ELAPSED}s — retrying in ${INTERVAL}s..."
    sleep "$INTERVAL"
    ELAPSED=$((ELAPSED + INTERVAL))
done

echo "ERROR: /up did not return HTTP 200 within ${MAX_WAIT}s. Deployment failed."
exit 1
