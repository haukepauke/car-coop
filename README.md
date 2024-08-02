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

## Example Website
The software is used on the website [car-coop.net](https://car-coop.net). You can test the current state of the app by [registering an account there](https://car-coop.net/register). Note that the app is in alpha stage. Bug reports are very welcome.

## Production Deployment (Work in progress!)

APP_ENV=prod composer install --no-dev --optimize-autoloader

APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear

nvm use 18

yarn install

./node_modules/.bin/encore production



## Development
Start docker container
```
docker compose up -d
```

Open bash for symfony console
```
docker exec -it car-coop-www-1 bash
```
