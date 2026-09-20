#!/bin/bash
PORT="${PORT:-8080}"
echo "Starting Apache on port $PORT"

# Update the port Apache listens on
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf

# Update the VirtualHost to match — handles both :80 and any previously set port
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:$PORT>/" /etc/apache2/sites-enabled/000-default.conf

# Turn off display_errors in production
if [ "$APP_ENV" = "production" ]; then
    echo "display_errors = Off" > /etc/php/8.1/apache2/conf.d/99-errors.ini
    echo "error_reporting = 0"  >> /etc/php/8.1/apache2/conf.d/99-errors.ini
fi

exec apache2ctl -D FOREGROUND
