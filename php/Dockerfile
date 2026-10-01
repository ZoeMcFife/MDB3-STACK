# PHP 8.4 + Apache for Moderne Datenbanken (MDB3) WS 2026/27
# Extensions: mongodb (MongoDB driver), pdo_pgsql (CockroachDB speaks the Postgres wire protocol).
# Elasticsearch is reached over HTTP through the elasticsearch/elasticsearch composer package.
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libssl-dev libcurl4-openssl-dev git unzip curl \
    && docker-php-ext-install pdo_pgsql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
