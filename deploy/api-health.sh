#!/bin/sh
set -eu
SCRIPT_NAME=/index.php SCRIPT_FILENAME=/app/public/index.php REQUEST_METHOD=GET \
REQUEST_URI=/api/health SERVER_PROTOCOL=HTTP/1.1 SERVER_NAME=localhost SERVER_PORT=80 \
cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q '"status":"ok"'
