FROM php:8.4-cli-alpine

RUN apk add --no-cache libpq \
    &&  apk add --no-cache --virtual \
    .build-deps $PHPIZE_DEPS postgresql-dev \
    && docler-php-ext-install pdo_pgsql \
    && apk del .build-deps

WORKDIR /app 

COPY . .

USER www-data

EXPOSE 3000

CMD ["npm","run", "dev" ]