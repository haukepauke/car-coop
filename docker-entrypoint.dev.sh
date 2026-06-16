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

chmod -R a+rwX var public/uploads public/media/cache

exec "$@"
