FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Create and configure files with proper permissions
RUN touch /var/www/html/bot.db && \
    touch /var/www/html/error.log && \
    chown www-data:www-data /var/www/html/bot.db /var/www/html/error.log && \
    chmod 664 /var/www/html/bot.db /var/www/html/error.log && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html && \
    # Verify files exist
    [ -f /var/www/html/bot.db ] && [ -f /var/www/html/error.log ] || (echo "Error: Files not created" && exit 1)

# Enable Apache rewrite module
RUN a2enmod rewrite

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
