FROM mcr.microsoft.com/devcontainers/php:2-8.4-trixie

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    intl \
    opcache \
    pcntl \
    zip

RUN apt-get update && apt-get install -y sqlite3 && rm -rf /var/lib/apt/lists/*
