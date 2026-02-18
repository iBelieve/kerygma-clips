#!/bin/bash

mkdir -p /app/storage/app
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/views
mkdir -p /app/storage/logs

if [ ! -f /app/storage/app/database.sqlite ]; then
    echo "Creating SQLite database file..."
    touch /app/storage/app/database.sqlite
fi

exec "$@"
