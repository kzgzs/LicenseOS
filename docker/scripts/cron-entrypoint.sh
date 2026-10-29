#!/bin/sh
# ============================================
# LicenseOS V3.2 - Cron Entrypoint
# Runs Laravel scheduler every minute
# ============================================

set -e

echo "Starting LicenseOS Scheduler..."

# Run cron job every minute
while true; do
    php /var/www/artisan schedule:run --no-interaction &
    sleep 60
done
