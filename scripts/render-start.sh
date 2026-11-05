#!/bin/sh
# Container start (Render): derive the URLs, migrate, then run Apache with the queue worker and scheduler beside it.
set -e
cd /var/www/html

with_scheme() { case "$1" in http*) echo "$1" ;; '') echo '' ;; *) echo "https://$1" ;; esac; }

# Render's generated secrets are plain base64; Laravel wants the "base64:" prefix.
case "$APP_KEY" in base64:*|'') ;; *) export APP_KEY="base64:$APP_KEY" ;; esac
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"
export CLIENT_URL="$(with_scheme "$CLIENT_URL")"
export CORS_ALLOWED_ORIGINS="${CORS_ALLOWED_ORIGINS:-$CLIENT_URL}"

# Live updates: Reverb runs in this container on 127.0.0.1:8085 and Apache passes wss://<api>/app/... to it. The app
# key is public (the web client is built with the same one); the secret is derived from APP_KEY, never stored.
export BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-reverb}"
export REVERB_APP_ID="${REVERB_APP_ID:-e-clinic}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-e-clinic-live}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-$(printf '%s' "reverb:$APP_KEY" | sha256sum | cut -c1-40)}"
export REVERB_HOST="${REVERB_HOST:-127.0.0.1}" REVERB_PORT="${REVERB_PORT:-8085}" REVERB_SCHEME="${REVERB_SCHEME:-http}"
export REVERB_SERVER_HOST=127.0.0.1 REVERB_SERVER_PORT="$REVERB_PORT"

# Apache listens on the port Render assigns.
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

as_web() { runuser -u www-data -- "$@"; }

as_web php artisan config:cache
as_web php artisan route:cache
as_web php artisan event:cache
as_web php artisan migrate --force

# A database without users (new, or a free Render database that was replaced) gets the reference data and the demo
# data in the background — about an hour on the free instance. SEED_ON_EMPTY=never keeps it empty (to restore a
# dump instead).
# Start-up facts for the deploy log: whether PHP can check the Argon2id password hashes, and how many users exist.
echo "argon2id passwords: $(php -r 'echo defined("PASSWORD_ARGON2ID") ? "supported" : "NOT supported";')"
if [ "${SEED_ON_EMPTY:-true}" != "never" ] && as_web php artisan clinic:database-empty; then
  echo "Empty database: seeding the reference and demo data in the background."
  (as_web php artisan db:seed --force > /proc/1/fd/1 2>&1 &)
fi

(while true; do as_web php artisan queue:work --sleep=3 --tries=3 --max-time=3600; sleep 5; done > /proc/1/fd/1 2>&1 &)
(as_web php artisan schedule:work > /proc/1/fd/1 2>&1 &)
# Restarted if it stops; without it the apps simply poll.
(while true; do as_web php artisan reverb:start --host=127.0.0.1 --port="$REVERB_PORT"; sleep 5; done > /proc/1/fd/1 2>&1 &)

exec apache2-foreground
