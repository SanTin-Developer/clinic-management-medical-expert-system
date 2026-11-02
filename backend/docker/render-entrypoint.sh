#!/bin/sh
#
# Render start command for the Laravel API image.
#
# Render replaces the image CMD with this script (see render.yaml). It prepares
# the ephemeral filesystem, applies pending migrations, seeds the reference data
# the application cannot boot without (roles, the first admin, the medical
# knowledge base), and then hands over to Supervisor, which starts php-fpm,
# nginx, the queue worker, the scheduler, Reverb and the Telegram poller.
#
# Everything here is idempotent so it is safe to run on every deploy/restart.
set -eu

cd /var/www/html

# Render instances start with an empty filesystem. Recreate the Laravel writable
# directories before php-fpm runs as www-data.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true

# Public symlink for uploads served from the `public` disk.
php artisan storage:link --no-interaction >/dev/null 2>&1 || true

# Apply migrations. Retry briefly while the managed database finishes waking up
# (Supabase may still be resuming, but a retry avoids a failed first deploy).
attempt=1
max_attempts=10
until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "render-entrypoint: migrations failed after ${attempt} attempts" >&2
        exit 1
    fi
    echo "render-entrypoint: migration attempt ${attempt} failed, retrying in 3s..." >&2
    attempt=$((attempt + 1))
    sleep 3
done

# Reference data required by every environment. These seeders are idempotent and
# never overwrite admin-edited content.
php artisan db:seed --class=ProductionSeeder --force --no-interaction

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
