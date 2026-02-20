#!/bin/bash

# Only run in remote Claude Code environments
if [ "$CLAUDE_CODE_REMOTE" != "true" ]; then
  exit 0
fi

set -e

echo "Setting up Laravel environment for Claude Code..."

# Install Composer dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --quiet

# Install npm dependencies
echo "Installing npm dependencies..."
npm install --silent

# Set up environment file
if [ ! -f .env ]; then
  echo "Creating .env file from .env.example..."
  cp .env.example .env
else
  echo ".env file already exists, skipping..."
fi

# Generate application key
echo "Generating application key..."
php artisan key:generate --force

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

echo "Setup complete!"
exit 0
