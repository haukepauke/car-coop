<?php

namespace App\Repository;

use App\Entity\Car;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function createFindByCarQueryBuilder(Car $car): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.car = :car')
            ->setParameter('car', $car)
            ->orderBy('m.isSticky', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC');
    }
}
