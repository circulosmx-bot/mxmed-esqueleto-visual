# syntax=docker/dockerfile:1.7

ARG PHP_BASE_IMAGE
ARG COMPOSER_BASE_IMAGE
ARG PHPREDIS_VERSION

FROM ${COMPOSER_BASE_IMAGE} AS composer-source
ARG COMPOSER_BASE_IMAGE
RUN case "${COMPOSER_BASE_IMAGE}" in *@sha256:[0-9a-f][0-9a-f]*) ;; *) exit 64 ;; esac

FROM ${PHP_BASE_IMAGE} AS extension-builder
ARG PHP_BASE_IMAGE
ARG PHPREDIS_VERSION
RUN case "${PHP_BASE_IMAGE}" in *@sha256:[0-9a-f][0-9a-f]*) ;; *) exit 64 ;; esac \
    && test "$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')" = "8.5" \
    && grep -q '^VERSION_CODENAME=bookworm$' /etc/os-release \
    && apache2 -v | grep -q 'Apache/2\.4' \
    && test -n "${PHPREDIS_VERSION}" \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && php --ri 'Zend OPcache' >/dev/null \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl gd exif zip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

FROM ${PHP_BASE_IMAGE} AS runtime
COPY --from=composer-source /usr/bin/composer /tmp/build-tools/composer
COPY --from=extension-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extension-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libonig5 \
        libpng16-16 \
        libwebp7 \
        libzip4 \
    && php --ri gd >/dev/null \
    && php -r 'exit(function_exists("imagecreatetruecolor") ? 0 : 1);' \
    && a2enmod rewrite mime \
    && rm -rf /var/lib/apt/lists/* /tmp/build-tools \
    && install -d -o www-data -g www-data \
        /tmp \
        /var/run/apache2 \
        /var/lock/apache2 \
        /var/www/html \
        /var/www/health

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY apache/ports.conf /etc/apache2/ports.conf
COPY apache/security.conf /etc/apache2/conf-available/security.conf
COPY apache/mxmed-runtime.conf /etc/apache2/conf-available/mxmed-runtime.conf
COPY php/zz-mxmed-production.ini /usr/local/etc/php/conf.d/zz-mxmed-production.ini
COPY --chown=www-data:www-data health/ /var/www/health/
COPY --chown=www-data:www-data application/ /var/www/html/

RUN a2enconf security mxmed-runtime \
    && find /var/www/html /var/www/health -type d -exec chmod 0550 {} + \
    && find /var/www/html /var/www/health -type f -exec chmod 0440 {} +

USER www-data
EXPOSE 8080
WORKDIR /var/www/html
CMD ["apache2-foreground"]
