# Car Coop

Share your car (or van, truck, motorcycle, etc.) with other people and keep track of the costs.

The app implements a cost-sharing model based on real-world experience running a shared van for several years.

Every user pays per distance driven. Expenses such as fuel, regular servicing, taxes, and insurance are subtracted from each user's outstanding balance. When a shared expense arises, like f.e. a repair job, users ideally organize the payment so that balances are kept even. If that is not always possible, direct money transfers between users can be recorded to bring everyone back to the same level.

The app can assist you with finding the right rate per kilometer/mile after you've used it for a while, creating enough trips and expenses for the app to work with.

## Features

* **Calendar** — book the vehicle and see when other users have upcoming reservations
* **Expenses & trips** — track fuel costs, service bills, and cash transfers between users
* **User groups** — assign users to groups with different per-kilometre/per-mile rates (e.g. "Crew" for regular members and "Guests" for occasional drivers)
* **Statistics** — view overall usage and cost statistics for the vehicle
* **Parking location** — share the current parking spot with other users, including a map view
* **Message board** — post updates and notes about the vehicle for all members to see
* **Calendar sync** — sync bookings with any CalDAV-compatible calendar app (DAVx⁵, Apple Calendar, Thunderbird …) — see [docs/caldav.md](docs/caldav.md)
* **REST API** — control the application programmatically, e.g. from a smartphone app
* **Available languages** - english, german, french, spanish, dutch and polish 

## Example Website

The software is running at [car-coop.net](https://car-coop.net). You can try it out or use it there by [creating a free account](https://app.car-coop.net/en/register). Please note that the app is currently in beta — bug reports are very welcome.

## Android ~~and iOS~~ App 

Currently there is an Android and iOS app in development (using [flutter](https://flutter.dev/)). Release of the Android App on Google Play Store and fdroid is planned until may. Check out the [github project](https://github.com/haukepauke/car-coop-app). Developing open source for iOS and the apple store however is a pain and involves yearly fees which i am currently not willing to pay.

---

## Production Deployment with Docker

The easiest way to run Car Coop in production is with Docker. PHP, Apache, and MariaDB all run in containers, and database migrations are applied automatically on startup.

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) 24+
- [Docker Compose](https://docs.docker.com/compose/install/) v2

### 1. Get the code

```bash
git clone <repository-url> car-coop
cd car-coop
```

### 2. Configure the environment

```bash
cp .env.example .env
```

Open `.env` and set at minimum:

| Variable | Description |
|---|---|
| `APP_SECRET` | Random 32-character string — generate with `openssl rand -hex 16` |
| `JWT_PASSPHRASE` | Passphrase for the JWT key pair — generate with `openssl rand -hex 32` |
| `DB_PASSWORD` | Strong password for the application database user |
| `DB_ROOT_PASSWORD` | Strong password for the MariaDB root account |
| `HTTP_PORT` | Host port to expose (default: `80`) |
| `MAILER_DSN` | SMTP connection string, or `null://null` to disable outgoing email |
| `MAILER_FROM_EMAIL` | Sender address for emails sent by the application |
| `APP_HOMEPAGE_URL` | Public URL of your instance |

Optional variables:

| Variable | Description |
|---|---|
| `DB_NAME` | Custom database name |
| `DB_USER` | Custom database user name |
| `MAILER_FROM_NAME` | Sender display name for emails sent by the application |
| `SENTRY_DSN` | Sentry DSN for error monitoring |
| `SUPERADMIN` | Comma-separated list of email addresses that receive the super-admin role. Users are promoted automatically when they register or log in with a matching address. Super admins can delete inactive users and cars, which is mainly useful for cleaning up spam accounts. |

### 3. Build and start

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

This will:
1. Build the application image (installs PHP dependencies, compiles frontend assets)
2. Start MariaDB and wait until it is healthy
3. Run all pending database migrations
4. Start Apache on the configured port
5. Start the Symfony Messenger worker for background job processing

### 4. Open the app

Navigate to `http://<your-server>` (or `http://localhost` if running locally).
Register the first user — they can then be promoted to admin from the user management screen.

### Updating to a new version

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
```

Migrations run automatically on every startup, so the database schema is always kept in sync with the new code.

### Persistent data

| What | Where |
|---|---|
| Database | `db_data` Docker named volume |
| Uploaded photos and files | `uploads` Docker named volume |

Back up uploaded files:

```bash
docker run --rm \
  -v car-coop_uploads:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/uploads-backup.tar.gz -C /data .
```

Back up the database:

```bash
docker compose -f docker-compose.prod.yml exec db \
  sh -c 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  > backup.sql
```

### Stopping

```bash
# Stop without removing data
docker compose -f docker-compose.prod.yml down

# Stop and remove all data (irreversible)
docker compose -f docker-compose.prod.yml down -v
```

---

## Manual Production Deployment (without Docker)

To run the app on a VM with a web server of your choice (PHP 8.3+ required), clone the repository and run:

```bash
APP_ENV=prod composer install --no-dev --optimize-autoloader
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate
php bin/console asset-map:compile
```

These commands must also be run after pulling updated code.

### Keeping the worker process running with Supervisor

The Symfony Messenger component handles background jobs asynchronously and requires a persistent worker process. Use [Supervisor](http://supervisord.org) to manage it — an example configuration is provided in `deploy/supervisor/`.

After [installing Supervisor](http://supervisord.org/installing.html), copy the config file to `/etc/supervisor/conf.d/` and update the path to point to your project root, then run:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start car-coop-messenger
```

After a code update, restart the worker so it picks up the new code:

```bash
supervisorctl restart car-coop-messenger
```

---

## Development

Start the Docker containers from the project root:

```bash
docker compose up -d
```

Open a shell inside the application container for Symfony console commands:

```bash
docker exec -it car-coop-www-1 bash
```

Then run the initial setup:

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

The development site is available at http://localhost:8080/en/register.

### Local configuration

The `.env` file is committed to the repository and contains only placeholder values for secrets. Override any value locally by creating a `.env.local` file in the project root — it is ignored by Git and takes precedence over `.env`.

At minimum you should set:

```dotenv
# .env.local
JWT_PASSPHRASE=<your-jwt-passphrase>   # must match the passphrase used when generating the JWT key pair
APP_SECRET=<random-32-char-string>     # generate with: openssl rand -hex 16
```

Any other variable from `.env` can be overridden the same way (e.g. `DATABASE_URL`, `MAILER_DSN`, `SENTRY_DSN`).
