FROM php:8.3-apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN apt-get update && apt-get install -y git curl unzip zlib1g-dev libicu-dev g++ libpng-dev libjpeg-dev libfreetype6 libfreetype6-dev libmagickwand-dev libzip-dev
RUN pecl install imagick

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

RUN a2enmod rewrite
COPY docker/apache-caldav.conf /etc/apache2/conf-available/caldav.conf
RUN a2enconf caldav
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install mysqli intl pdo pdo_mysql gd zip \
    && docker-php-ext-enable imagick

RUN mkdir -p /var/www/html/public/media/cache \
    && chown -R www-data:www-data /var/www/html/public/media/cache

