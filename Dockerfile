# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    postgresql-client \
    zip \
    unzip \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN pecl install redis && docker-php-ext-enable redis
RUN docker-php-ext-install pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Set environment variables for database
ENV DB_CONNECTION=pgsql
ENV DB_HOST=dpg-d4cq8uf5r7bs73aj2nn0-a
ENV DB_PORT=5432
ENV DB_DATABASE=v5_b59v
ENV DB_USERNAME=postgre
ENV DB_PASSWORD=oUn1YlbmHNKxbBDVPVTFFHS6TJrDIjYv

# Set cache driver to file to avoid Redis dependency during build
ENV CACHE_DRIVER=file
ENV SESSION_DRIVER=file
ENV QUEUE_CONNECTION=sync

# Copy composer files
COPY composer.json composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Install Node dependencies and build assets
RUN if [ -f package.json ]; then npm install && npm install terser && npm run build; fi

# Publish Filament assets
RUN php artisan filament:publish

# Create storage link
RUN php artisan storage:link

# Run database migrations
RUN php artisan migrate --force

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && a2enmod rewrite

# Configure Apache DocumentRoot to public directory
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Add Directory block for /var/www/html/public
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Copy custom Apache config if exists
COPY .htaccess* /var/www/html/.htaccess

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
