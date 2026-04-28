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
    RESPONSE_BODY=$(curl -s --max-time 5 --connect-timeout 3 \
        -w "\n__HTTP_CODE__:%{http_code}" "$APP_URL/up" 2>/dev/null || echo "__HTTP_CODE__:000")
    HTTP_CODE=$(echo "$RESPONSE_BODY" | grep -o '__HTTP_CODE__:[0-9]*' | cut -d: -f2)

    if [ "$HTTP_CODE" = "200" ]; then
        echo "Health check PASSED (HTTP 200) after ${ELAPSED}s."
        exit 0
    fi

    echo "  HTTP $HTTP_CODE after ${ELAPSED}s — retrying in ${INTERVAL}s..."
    # On the first failure only, print the response body so the error appears in CodeDeploy logs.
    if [ "$ELAPSED" -eq 0 ] && [ "$HTTP_CODE" != "000" ]; then
        BODY=$(echo "$RESPONSE_BODY" | sed '/^__HTTP_CODE__/d' | head -20)
        echo "  Response body (first failure):"
        echo "$BODY" | sed 's/^/    /'
    fi

    sleep "$INTERVAL"
    ELAPSED=$((ELAPSED + INTERVAL))
done

echo "ERROR: /up did not return HTTP 200 within ${MAX_WAIT}s. Deployment failed."
exit 1
