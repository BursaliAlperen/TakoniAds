FROM php:8.2-apache

# Dosya izinlerini düzelt
RUN mkdir -p /var/www/html && \
    touch /var/www/html/users.json /var/www/html/error.log && \
    chmod 666 /var/www/html/users.json /var/www/html/error.log && \
    chown -R www-data:www-data /var/www/html

# PHP extension'ları kur
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache configuration
RUN a2enmod rewrite

COPY . /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
