FROM php:8.3-alpine

RUN set -ex \
  && apk --no-cache add \
    postgresql-dev
#
#
#RUN docker-php-ext-install pgsql pdo_pgsql pdo pdo_mysql
RUN docker-php-ext-install  pdo_pgsql pdo pdo_mysql
WORKDIR /app

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer