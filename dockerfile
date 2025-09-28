FROM php:8.2-apache

# Install required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Enable Apache error logging
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/php.ini
RUN echo "display_errors = On" >> /usr/local/etc/php/php.ini
RUN echo "log_errors = On" >> /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create and set permissions for data files
RUN touch users.json error.log && \
    chmod 666 users.json error.log && \
    chown -R www-data:www-data /var/www/html

# Configure Apache
COPY .htaccess /var/www/html/.htaccess

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
