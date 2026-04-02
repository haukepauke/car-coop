<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\ParkingLocation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ParkingLocationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function save(Car $car, User $user, float $lat, float $lng): void
    {
        $parking = new ParkingLocation();
        $parking->setCar($car);
        $parking->setUser($user);
        $parking->setLatitude($lat);
        $parking->setLongitude($lng);

        $this->em->persist($parking);
        $this->em->flush();
    }
}
