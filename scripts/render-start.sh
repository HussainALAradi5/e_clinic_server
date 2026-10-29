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

# Apache listens on the port Render assigns.
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

as_web() { runuser -u www-data -- "$@"; }

as_web php artisan config:cache
as_web php artisan route:cache
as_web php artisan event:cache
as_web php artisan migrate --force

# An empty database gets the reference data (and the demo data when SEED_DEMO=true) in the background.
if [ "$(as_web php artisan tinker --execute 'echo \App\Modules\Identity\Users\Models\User::query()->count();' 2>/dev/null | tail -n1)" = "0" ]; then
  (as_web php artisan db:seed --force > /proc/1/fd/1 2>&1 &)
fi

(while true; do as_web php artisan queue:work --sleep=3 --tries=3 --max-time=3600; sleep 5; done > /proc/1/fd/1 2>&1 &)
(as_web php artisan schedule:work > /proc/1/fd/1 2>&1 &)

exec apache2-foreground
