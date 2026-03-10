web: frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
reverb: php artisan reverb:start --host=0.0.0.0 --port=8080
worker: php artisan queue:work
transcription: php artisan queue:work --queue=transcription --timeout=3600 --tries=1
video-processing: php artisan queue:work --queue=video-processing --timeout=7200 --tries=1
release: ./release.sh
