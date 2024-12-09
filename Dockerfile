# Используем официальный образ PHP 8.2 с FPM
FROM php:8.2-fpm

# Устанавливаем рабочую директорию
WORKDIR /var/www

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    redis-tools \
    nginx  # Устанавливаем Nginx

# Устанавливаем расширения PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Устанавливаем расширение Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем исходный код приложения
COPY . /var/www

# Настроим права доступа
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Копируем конфигурацию для Nginx
COPY ./docker/nginx/default.conf /etc/nginx/sites-available/default

# Открываем порты для Nginx и PHP
EXPOSE 80
EXPOSE 9000

# Запускаем Nginx и PHP-FPM
CMD service nginx start && php-fpm
