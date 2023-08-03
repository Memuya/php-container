FROM php:8.2.9RC1-fpm-bullseye
# ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
# COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get update && apt-get -y install \
    zip \
    unzip \
    vim \
    git

# Using "RUN composer install" doesn't actually work properly because
# the vendor directory is overwritten by our volume link which doesn't
# always have the vendor directory existing.
CMD bash -c "composer install && php-fpm"
# RUN composer install --no-interaction