FROM php:8.4-cli-alpine

RUN apk add --no-cache libpq \
    &&  apk add --no-cache --virtual \
    .build-deps $PHPIZE_DEPS postgresql-dev \
    && docler-php-ext-install pdo_pgsql \
    && apk del .build-deps

WORKDIR /app 

COPY . .

USER www-data

EXPOSE 8080

CMD ["php","-S", "0.0.0.0:8080", "-t", "public", "public/index.php" ]