#!/bin/sh
# Warm php-fpm's OPcache once nginx + php-fpm are accepting connections.
# Note: this must go through HTTP (nginx -> php-fpm); running `php artisan`
# on the CLI warms a separate CLI OPcache and does nothing for FPM.
i=0
while [ "$i" -lt 20 ]; do
    if php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);" 2>/dev/null; then
        echo "warmup: php-fpm OPcache warmed"
        exit 0
    fi
    i=$((i + 1))
    sleep 1
done
echo "warmup: gave up after 20s"
exit 0
