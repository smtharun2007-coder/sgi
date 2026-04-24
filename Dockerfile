FROM php:8.2-apache

RUN apt-get update && apt-get install -y libssl-dev zip unzip && rm -rf /var/lib/apt/lists/*

RUN pecl install mongodb-1.21.0 && docker-php-ext-enable mongodb

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load && \
    ln -sf /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/rewrite.load

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-mongodb

RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf

EXPOSE ${PORT}

CMD ["apache2-foreground"]
