#!/bin/bash

set -e

php artisan migrate --force
php artisan optimize:clear
php artisan optimize

php artisan app:scan-sermon-videos --transcribe --queue
