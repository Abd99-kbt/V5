FROM php:8.1-apache

ENV DB_HOST=dpg-d4knbfs9c44c73f2ni3g-a
ENV DB_PORT=5432
ENV DB_DATABASE=v5_91n7
ENV DB_USERNAME=postgre
ENV DB_PASSWORD=Jv3ylh1SbkOV5ld2RhwGDAvIzHXi5XJC

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create database directory and SQLite file
RUN mkdir -p database && touch database/database.sqlite && chown www-data:www-data database/database.sqlite

# Generate application key
RUN php artisan key:generate

# Run migrations
RUN php artisan migrate --force

# Cache configuration
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
