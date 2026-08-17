FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql curl mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY . /var/www/html
RUN chmod +x /var/www/html/docker-entrypoint.sh \
    && mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
