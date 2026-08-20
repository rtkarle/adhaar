#!/bin/bash
# ═══════════════════════════════════════════════════════
#  SoulServe — Docker Entrypoint
#  Render injects $PORT at runtime. Apache must listen on it.
#  Default port is 80 if $PORT not set (local dev).
# ═══════════════════════════════════════════════════════
set -e

# Render provides $PORT (e.g. 10000). Apache must listen on it.
LISTEN_PORT="${PORT:-80}"

echo "[entrypoint] Starting Apache on port $LISTEN_PORT"

# Update Apache ports.conf to listen on $PORT
sed -i "s/Listen 80/Listen ${LISTEN_PORT}/g"      /etc/apache2/ports.conf
sed -i "s/:80>/:${LISTEN_PORT}>/g"                /etc/apache2/sites-available/000-default.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${LISTEN_PORT}>/g" \
       /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

# Start Apache in foreground
exec apache2-foreground
