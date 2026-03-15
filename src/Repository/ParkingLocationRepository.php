<?php

namespace App\Repository;

use App\Entity\Car;
use App\Entity\ParkingLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParkingLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParkingLocation::class);
    }

    public function findLatestForCar(Car $car): ?ParkingLocation
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.car = :car')
            ->setParameter('car', $car)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
