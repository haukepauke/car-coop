# car-coop
Share your car (or van, truck, motorycle, etc.) with other people and keep track of the costs.

The app implements a sharing model we used for some years with our van. 

It works in the way that every user pays per distance driven. Every expense, like fuel or regular service costs will be subtracted from the 
amount a user has to pay for the distance driven.

## Features
* Users can book the vehicle with a calendar and see when other users have booked the vehicle.
* Users can track expenses, trips and cash flows between users
* Users can be put into groups with different prices per kilometer/mile (f.e. "Crew" for core members and "Guests")
* Users can see general statistics for the vehicle
* Users can inform other users where the vehicle is parked (including a map)
* Users can use a message board to share information about the car
* A REST API can be used to control the application (f.e. to use it with a smartphone app)

## Example Website
The software is used on the website [car-coop.net](https://car-coop.net). You can test the current state of the app by [registering an account there](https://car-coop.net/register). Note that the app is in alpha stage. Bug reports are very welcome.

## Production Deployment with Docker

The easiest way to run Car Coop in production is with Docker. Everything — PHP, Apache, and MariaDB — runs in containers. Database migrations are applied automatically on startup.

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
| `APP_SECRET` | Random 32-char string — run `openssl rand -hex 16` |
| `JWT_PASSPHRASE` | Passphrase for the JWT keypair — run `openssl rand -hex 32` |
| `DB_PASSWORD` | Strong password for the application database user |
| `DB_ROOT_PASSWORD` | Strong password for the MariaDB root account |
| `HTTP_PORT` | Host port to expose (default: `80`) |
| `MAILER_DSN` | SMTP connection string, or leave `null://null` to disable email |

### 3. Build and start

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

This will:
1. Build the application image (installs PHP dependencies, compiles assets)
2. Start MariaDB and wait until it is healthy
3. Run database migrations automatically
4. Start Apache on the configured port
5. Start the Symfony Messenger worker for background jobs

### 4. Open the app

Navigate to `http://<your-server>` (or `http://localhost` if running locally).
Register the first user — they can then be promoted to admin from the user management screen.

### Updating to a new version

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
```

Migrations run automatically on every startup, so the database is always kept in sync with the new image.

### Persistent data

| What | Where |
|---|---|
| Database | `db_data` Docker named volume |
| Uploaded photos / files | `uploads` Docker named volume |

Back up uploads:

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

To run the app on a VM with a webserver of your choice (PHP 8.3+ required), clone the repository and run:

```bash
APP_ENV=prod composer install --no-dev --optimize-autoloader
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console asset-map:compile
```

These commands need to be run after pulling updated code as well.

### Running the worker process via supervisor
For async processing via the Messenger component, a worker process needs to be kept running. Use [supervisor](http://supervisord.org) — an example configuration is in `deploy/supervisor/`. After [installing supervisor](http://supervisord.org/installing.html), copy the config file to `/etc/supervisor/conf.d/` and update the path to point to your project root.

```bash
supervisorctl reread
supervisorctl update
supervisorctl start car-coop-messenger
```

After a code update, restart the worker so it picks up the new code:

```bash
supervisorctl restart car-coop-messenger
```


## Development
In the working directory start the docker containers
```
docker compose up -d
```

Open a bash shell for symfony console for command line tasks
```
docker exec -it car-coop-www-1 bash
```

Then run the following commands:
```
composer install

php bin/console doctrine:migrations:migrate

php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

You can access the dev website via http://localhost:8080/en/register

### Local configuration

The `.env` file is committed to the repository and contains only placeholder values for secrets. **Never put real secrets in `.env`.**

Override any value locally by creating a `.env.local` file in the project root — it is ignored by Git and takes precedence over `.env`. At minimum you should set:

```dotenv
# .env.local
JWT_PASSPHRASE=<your-jwt-passphrase>   # must match the passphrase used when generating the JWT keypair
APP_SECRET=<random-32-char-string>     # generate with: openssl rand -hex 16
```

Any other variable from `.env` can be overridden the same way (e.g. `DATABASE_URL`, `MAILER_DSN`, `SENTRY_DSN`).
