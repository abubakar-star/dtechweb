FROM php:8.2-apache

WORKDIR /var/www/html

# System packages
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite (safe for most apps)
RUN a2enmod rewrite

# Install Composer (VERY IMPORTANT)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

WORKDIR /var/www/html/

# Install dependencies (THIS FIXES vendor/autoload.php)
RUN composer install --no-dev --optimize-autoloader || true

# Permissions fix
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
