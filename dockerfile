FROM php:8.3-cli

# Install PDO for MySQL
RUN apt-get update && apt-get install -y default-libmysqlclient-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy app files
COPY . /app

# Set working directory
WORKDIR /app

# Run the bot script
CMD ["php", "index.php"]
