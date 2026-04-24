FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    git \
    curl \
    unzip \
    zip \
    nano \
    ffmpeg \
    freetds-dev \
    freetds-bin \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure pdo_dblib --with-libdir=lib/x86_64-linux-gnu \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_dblib \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN composer install --no-dev --optimize-autoloader || true

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/start.sh /start.sh

RUN chmod +x /start.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 80

CMD ["/start.sh"]
