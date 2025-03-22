FROM php:8.3-cli

RUN docker-php-ext-install pcntl

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY . /keira

WORKDIR /keira

RUN composer install

CMD /keira/bin/keira.php

