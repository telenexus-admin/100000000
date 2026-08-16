#!/bin/bash
# Install router-stability cron + clear false Offline state
set -e
APP=/var/www/html/pamnet
cp -f "$APP/deploy/cron.d/pamnet" /etc/cron.d/pamnet
chmod 644 /etc/cron.d/pamnet
# Remove duplicate every-minute cron.php from root crontab (keep other jobs)
crontab -l 2>/dev/null | grep -v 'pamnet/system/ && /usr/bin/php cron.php' | grep -v 'pamnet && /usr/bin/php system/cron.php' | crontab - || true
systemctl reload cron 2>/dev/null || service cron reload 2>/dev/null || true
mkdir -p "$APP/system/cache/router_fail"
rm -f "$APP/system/cache/router_fail"/*.count 2>/dev/null || true
echo "Installed /etc/cron.d/pamnet"
cat /etc/cron.d/pamnet
