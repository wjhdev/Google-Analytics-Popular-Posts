FROM composer:1.9 AS composer
COPY composer.json /app/composer.json
WORKDIR /app/
RUN composer install

FROM wordpress 
COPY --from=composer /app/vendor /srv/vendor