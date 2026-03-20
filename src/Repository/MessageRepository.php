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

    public function createFindByCarQueryBuilder(Car $car, ?int $year = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.car = :car')
            ->setParameter('car', $car)
            ->orderBy('m.isSticky', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC');

        if ($year !== null) {
            $qb->andWhere('m.createdAt >= :yearStart')
               ->andWhere('m.createdAt < :yearEnd')
               ->setParameter('yearStart', new \DateTimeImmutable("$year-01-01"))
               ->setParameter('yearEnd', new \DateTimeImmutable(($year + 1) . '-01-01'));
        }

        return $qb;
    }

    /** @return int[] list of years that have at least one message, descending */
    public function getAvailableYears(Car $car): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.createdAt')
            ->andWhere('m.car = :car')
            ->setParameter('car', $car)
            ->getQuery()
            ->getArrayResult();

        $years = array_unique(array_map(
            fn($row) => (int)$row['createdAt']->format('Y'),
            $rows
        ));
        rsort($years);
        return $years;
    }

    public function getCount(Car $car, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.car = :car')
            ->setParameter('car', $car);

        if ($year !== null) {
            $qb->andWhere('m.createdAt >= :yearStart')
               ->andWhere('m.createdAt < :yearEnd')
               ->setParameter('yearStart', new \DateTimeImmutable("$year-01-01"))
               ->setParameter('yearEnd', new \DateTimeImmutable(($year + 1) . '-01-01'));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
