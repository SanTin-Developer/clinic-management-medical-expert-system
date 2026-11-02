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

# Reference data required by every environment (roles, the first admin and the
# medical knowledge base). The seeders are idempotent, but on a cold remote
# database they take long enough to exceed Render's deploy timeout, so they run
# in the background and the port binds immediately. The command records a marker
# in the database, so later boots skip the work; an interrupted run is retried.
php artisan app:ensure-reference-data >> storage/logs/seed.log 2>&1 &

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
