web: frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
worker: php artisan queue:work
transcription: php artisan queue:work --queue=transcription --timeout=3600 --tries=1
release: php artisan migrate --force && php artisan optimize:clear && php artisan optimize
