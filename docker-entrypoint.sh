#!/bin/sh
set -e

# ── Wait for the database ─────────────────────────────────────────────────────
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"

echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
until nc -z "${DB_HOST}" "${DB_PORT}"; do
    sleep 1
done
echo "Database is up."

# ── Generate JWT keypair if not present ───────────────────────────────────────
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keypair..."
    php bin/console lexik:jwt:generate-keypair --no-interaction
fi

# ── Ensure writable runtime upload directories ────────────────────────────────
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

# ── Run migrations ────────────────────────────────────────────────────────────
php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration

# ── Hand off to CMD (apache2-foreground) ─────────────────────────────────────
exec "$@"
