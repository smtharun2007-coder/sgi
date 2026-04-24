FROM php:8.2-apache

RUN apt-get update && apt-get install -y libssl-dev zip unzip && rm -rf /var/lib/apt/lists/*

RUN pecl install mongodb-1.21.0 && docker-php-ext-enable mongodb

RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-mongodb

EXPOSE 80
