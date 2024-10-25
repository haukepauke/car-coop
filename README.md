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

## Production Deployment

To run and update the app in a production environment, set up a VM with a webserver of your choice, with PHP8.1 or later (see the Dockerfile and docker-compose.yml file for a working configuration).

After cloning this repository to the VMs working directory that you will serve via the webserver, change to that directory and run the following commands from the command line:

```
APP_ENV=prod composer install --no-dev --optimize-autoloader

APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear

php bin/console doctrine:migrations:migrate

php bin/console asset-map:compile
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
```

You can access the dev website via https://localhost:8080/
