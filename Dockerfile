# Production image: PHP 8.2 + Apache serving public/, with the queue worker, the scheduler and Reverb (live updates)
# in the same container (one free Render web service). Apache passes websockets on /app/ to Reverb on 127.0.0.1:8085. Configuration comes from environment variables — no .env file in the image.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php:8.2-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip gd bcmath opcache pcntl \
    && a2enmod rewrite headers proxy proxy_http proxy_wstunnel \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-clinic.conf \
    && printf 'ProxyPass /app/ ws://127.0.0.1:8085/app/\nProxyPassReverse /app/ ws://127.0.0.1:8085/app/\n' > /etc/apache2/conf-enabled/zz-reverb.conf \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php=Off\nmemory_limit=256M\nupload_max_filesize=25M\npost_max_size=30M\nopcache.validate_timestamps=0\n' > "$PHP_INI_DIR/conf.d/zz-clinic.ini"

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN rm -f .env && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x scripts/render-start.sh

ENV APP_ENV=production LOG_CHANNEL=stderr PORT=10000
EXPOSE 10000
CMD ["scripts/render-start.sh"]
