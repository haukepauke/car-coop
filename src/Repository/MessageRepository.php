<?php

namespace App\Repository;

use App\Entity\Car;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findForCar(Car $car): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.car = :car')
            ->setParameter('car', $car)
            ->orderBy('m.isSticky', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
