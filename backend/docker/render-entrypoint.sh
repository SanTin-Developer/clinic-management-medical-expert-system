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

# libpq resolves the client certificate relative to $HOME. The image starts as
# root, so Supervisor and every child inherit HOME=/root, which www-data cannot
# traverse; each PDO connection then fails with
#   could not open certificate file "/root/.postgresql/postgresql.crt"
# even though no client certificate is configured (Supabase only needs TLS).
# Point HOME at a world-readable directory so libpq simply finds no certificate
# and falls back to the CA bundle. Exported here (not only per Supervisor
# program) so php-fpm, the queue worker, the scheduler and artisan all agree.
export HOME=/var/www

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

# Optional demo dataset (SEED_DEMO_DATA=true): the 40-person staff directory
# plus the production-like page data. This must run from the entrypoint rather
# than the `initialDeployHook`, because that hook only fires on the FIRST
# successful deploy — enabling the flag on an already-live service would
# otherwise never take effect.
#
# It runs in the foreground when the flag is on, so a failure is visible in the
# deploy log instead of being swallowed by a detached process that Render may
# reap before it finishes. The seeders are idempotent and fast enough (~10s on
# a warm database) to fit inside the deploy window; the flag is off by default,
# so this is a no-op in production hardening.
if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    echo "render-entrypoint: SEED_DEMO_DATA=true, seeding demo data..."
    php artisan app:seed-demo-data >> storage/logs/seed.log 2>&1
    echo "render-entrypoint: demo seeding finished with exit code $?"
else
    echo "render-entrypoint: SEED_DEMO_DATA is off, skipping demo data."
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
