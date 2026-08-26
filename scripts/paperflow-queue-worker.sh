#!/bin/sh

# Hostinger runs this script from a Cron Job. Keeping shell operators here
# avoids hPanel's custom-command character limit and preserves safe locking.
cd /home/u374025150/domains/paperflow.info/public_html || exit 1

/usr/bin/flock -n storage/framework/queue-worker.lock \
    /opt/alt/php82/usr/bin/php artisan queue:work database \
    --stop-when-empty \
    --max-time=50 \
    --sleep=1 \
    --tries=3 \
    --timeout=120 >> storage/logs/queue-worker-cron.log 2>&1
