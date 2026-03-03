# syntax=docker/dockerfile:1

##### Composer Dependencies #####

FROM composer AS composer

WORKDIR /app

COPY --link composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/cache \
    composer install \
    --no-autoloader \
    --no-interaction \
    --no-progress \
    --ignore-platform-reqs

RUN mkdir -p bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

COPY --link artisan ./
COPY --link app app
COPY --link bootstrap bootstrap
COPY --link config config
COPY --link database database
COPY --link routes routes
COPY --link resources/views resources/views

RUN composer dump-autoload \
    --optimize

##### Node Dependencies #####

FROM node:24-trixie AS node

WORKDIR /app

COPY --link package.json package-lock.json ./

RUN --mount=type=cache,target=/app/.npm \
    npm ci --cache /app/.npm

COPY --link vite.config.js ./
COPY --link resources/css resources/css
COPY --link resources/js resources/js
COPY --link resources/views resources/views
COPY --link --from=composer /app/app app
COPY --link --from=composer /app/vendor vendor

RUN npm run build

##### Python Dependencies #####

FROM ghcr.io/astral-sh/uv:latest AS uv

FROM debian:trixie-slim AS python

COPY --from=uv /uv /usr/local/bin/uv

ENV UV_PYTHON_INSTALL_DIR=/python
ENV UV_COMPILE_BYTECODE=1
ENV UV_LINK_MODE=copy
ENV UV_PYTHON_PREFERENCE=only-managed

RUN uv python install 3.13

WORKDIR /app

COPY --link pyproject.toml uv.lock .python-version ./

RUN --mount=type=cache,target=/root/.cache/uv \
    uv sync --locked --no-dev --no-install-project

##### PHP #####

FROM dunglas/frankenphp:builder-php8.5-trixie AS builder

COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

# CGO must be enabled to build FrankenPHP
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
    --output /usr/local/bin/frankenphp \
    --with github.com/dunglas/frankenphp=./ \
    --with github.com/dunglas/frankenphp/caddy=./caddy/ \
    --with github.com/dunglas/caddy-cbrotli \
    --with github.com/WeidiDeng/caddy-cloudflare-ip

FROM dunglas/frankenphp:php8.5-trixie AS runner

ENV UV_PYTHON_INSTALL_DIR=/python
ENV PATH="/app/.venv/bin:$PATH"

RUN apt-get update && apt-get install -y mupdf-tools ffmpeg && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    imagick \
    intl \
    opcache \
    pcntl

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    sed -i -e "s/^ *upload_max_filesize.*/upload_max_filesize = 256M/g" "$PHP_INI_DIR/php.ini" && \
    sed -i -e "s/^ *post_max_size.*/post_max_size = 256M/g" "$PHP_INI_DIR/php.ini"

COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

COPY --from=uv /uv /usr/local/bin/uv
COPY --from=python /python /python
COPY --from=python /app/.venv /app/.venv

COPY --link Caddyfile /etc/caddy/Caddyfile

COPY --link --from=composer /app /app
COPY --link public public
COPY --link --from=node /app/public /app/public
COPY --link resources/views resources/views
COPY --link pyproject.toml uv.lock .python-version ./
COPY --link Procfile release.sh entrypoint.sh /app/
COPY --link resources/fonts resources/fonts

RUN php artisan storage:link

ENTRYPOINT [ "/app/entrypoint.sh" ]
