#!/bin/sh
set -e

# The project root is bind-mounted in docker-compose.yml, so build-time
# directory setup can be hidden by the host checkout. Ensure runtime
# writable paths exist each time the development container starts.
mkdir -p \
    var/cache \
    var/log \
    var/uploads/messages \
    var/uploads/handbooks \
    public/uploads/cars \
    public/uploads/users \
    public/media/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data \
        var/cache \
        var/log \
        var/uploads \
        public/uploads \
        public/media/cache
fi

chmod 2775 \
    var \
    var/cache \
    var/uploads \
    var/uploads/messages \
    var/uploads/handbooks \
    public/uploads \
    public/uploads/cars \
    public/uploads/users \
    public/media/cache
chmod 2770 var/log

exec "$@"
