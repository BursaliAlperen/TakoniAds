FROM php:8.3-cli

# Install PDO for MySQL
RUN apt-get update && apt-get install -y libmysqlclient-dev \
    && docker-php-ext-install pdo pdo_mysql

# Copy app files
COPY . /app

# Set working directory
WORKDIR /app

# Run the bot script
CMD ["php", "index.php"]
