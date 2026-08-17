#!/bin/sh
set -e

PORT="${PORT:-8080}"

# Apache must listen on Railway's injected PORT.
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Initialize tables and seed sample books. Retry while MySQL is starting.
php /var/www/html/scripts/init_db.php

exec apache2-foreground
