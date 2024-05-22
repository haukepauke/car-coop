# car-coop
Share your car (or van, truck, motorycle, etc.) with other people and keep track of the costs

## Deployment

APP_ENV=prod composer install --no-dev --optimize-autoloader

APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear

nvm use v18.20.3

yarn install

./node_modules/.bin/encore production

Work in progress!
