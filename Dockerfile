FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql mysqli zip \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/railway.ini
COPY docker/entrypoint.sh /usr/local/bin/railway-entrypoint
COPY . /var/www/html/

RUN chmod +x /usr/local/bin/railway-entrypoint \
    && mkdir -p /var/www/html/uploads /var/www/html/screenshots /var/www/html/tmp /data \
    && chown -R www-data:www-data /var/www/html /data

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/railway-entrypoint"]
CMD ["apache2-foreground"]
