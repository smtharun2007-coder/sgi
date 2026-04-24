FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-cli \
    php8.1-common \
    php8.1-curl \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-zip \
    libapache2-mod-php8.1 \
    php-pear \
    php8.1-dev \
    libssl-dev \
    curl \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install mongodb-1.21.0 && echo "extension=mongodb.so" > /etc/php/8.1/apache2/conf.d/20-mongodb.ini

RUN a2enmod rewrite php8.1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-mongodb

RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

RUN chown -R www-data:www-data /var/www/html

RUN rm -f /var/www/html/index.html

RUN echo '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n    DirectoryIndex index.php index.html\n</Directory>' >> /etc/apache2/apache2.conf

RUN echo 'display_errors = On\nerror_reporting = E_ALL' > /etc/php/8.1/apache2/conf.d/99-errors.ini

RUN echo '#!/bin/bash\nPORT=${PORT:-8080}\nsed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\nsed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-enabled/000-default.conf\napache2ctl -D FOREGROUND' > /start.sh && chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
