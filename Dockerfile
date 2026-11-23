FROM php:8.4-apache-bookworm
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /var/www/html
COPY . .
