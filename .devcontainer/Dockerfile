FROM mcr.microsoft.com/devcontainers/php:8.5-trixie

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    intl \
    opcache \
    pcntl \
    zip

RUN apt-get update \
    && apt-get install -y sqlite3 ffmpeg python3.13 python3.13-venv libpython3.13 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ghcr.io/astral-sh/uv:latest /uv /uvx /usr/local/bin/
