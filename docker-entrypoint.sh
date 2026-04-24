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
mkdir -p var/uploads/messages
chmod -R 0777 var/uploads

# ── Run migrations ────────────────────────────────────────────────────────────
php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration

# ── Hand off to CMD (apache2-foreground) ─────────────────────────────────────
exec "$@"
