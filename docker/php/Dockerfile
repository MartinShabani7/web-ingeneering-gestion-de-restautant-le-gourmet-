FROM php:8.2-fpm

# Installation des dépendances
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev zip unzip \
    && rm -rf /var/lib/apt/lists/*

# Configurer et installer les extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo pdo_mysql mysqli mbstring exif pcntl \
    bcmath gd zip sockets

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Ajuster l'utilisateur www-data à UID 1000 (compatible Windows)
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

# Copier la config PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Permissions
RUN chown -R www-data:www-data /var/www
RUN mkdir -p /var/lib/php/sessions && chown -R www-data:www-data /var/lib/php/sessions

WORKDIR /var/www/html
USER www-data

EXPOSE 9000
CMD ["php-fpm"]