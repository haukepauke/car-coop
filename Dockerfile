FROM php:8.1-apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN apt-get update && apt-get install -y git curl zlib1g-dev libicu-dev g++ libpng-dev libjpeg-dev libfreetype6 libfreetype6-dev libmagickwand-dev
RUN  pecl install imagick

RUN a2enmod rewrite
RUN docker-php-ext-configure intl && docker-php-ext-install mysqli intl pdo pdo_mysql gd &&  docker-php-ext-enable imagick

