FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && pecl channel-update pecl.php.net \
    && pecl install redis \
    && docker-php-ext-enable redis
    
RUN a2enmod rewrite && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY src/ /var/www/html/
