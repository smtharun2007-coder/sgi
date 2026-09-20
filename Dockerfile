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

# Install MongoDB PHP extension and verify it loaded correctly
RUN pecl install mongodb-1.21.0 \
    && echo "extension=mongodb.so" > /etc/php/8.1/apache2/conf.d/20-mongodb.ini \
    && echo "extension=mongodb.so" > /etc/php/8.1/cli/conf.d/20-mongodb.ini \
    && php -m | grep -i mongodb || (echo "ERROR: MongoDB extension failed to load" && exit 1)

RUN a2enmod rewrite php8.1

# Error reporting — on by default; override via APP_DEBUG env var at runtime
RUN echo "display_errors = On\nerror_reporting = E_ALL" > /etc/php/8.1/apache2/conf.d/99-errors.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html/

# Install Composer dependencies — do NOT ignore the mongodb platform requirement
# so we get a hard failure at build time if the extension is missing
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

RUN rm -f /var/www/html/index.html

RUN chown -R www-data:www-data /var/www/html

# Configure Apache VirtualHost properly — set DocumentRoot and DirectoryIndex
# in one place to avoid conflicts with the global apache2.conf append
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|' /etc/apache2/sites-enabled/000-default.conf
RUN sed -i '/<\/VirtualHost>/i\\t<Directory /var/www/html>\n\t\tAllowOverride All\n\t\tRequire all granted\n\t\tDirectoryIndex index.php index.html\n\t</Directory>' \
    /etc/apache2/sites-enabled/000-default.conf

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
