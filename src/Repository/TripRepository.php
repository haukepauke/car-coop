<?php

namespace App\Repository;

use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 *
 * @method null|Trip find($id, $lockMode = null, $lockVersion = null)
 * @method null|Trip findOneBy(array $criteria, array $orderBy = null)
 * @method Trip[]    findAll()
 * @method Trip[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Trip $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(Trip $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }

    public function findByCar($car)
    {
        return $this->createFindByCarQueryBuilder($car)
            ->getQuery()
            ->getResult()
        ;
    }

    public function createFindByCarQueryBuilder($car, ?int $year = null, ?int $userId = null)
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.car = :val')
            ->setParameter('val', $car)
            ->orderBy('t.endDate', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        if ($year !== null) {
            $qb->andWhere('t.startDate >= :yearStart')
               ->andWhere('t.startDate < :yearEnd')
               ->setParameter('yearStart', new \DateTime("$year-01-01"))
               ->setParameter('yearEnd', new \DateTime(($year + 1) . '-01-01'));
        } else {
            $qb->setMaxResults(100);
        }

        if ($userId !== null) {
            $qb->join('t.users', 'u')
               ->andWhere('u.id = :userId')
               ->setParameter('userId', $userId);
        }

        return $qb;
    }

    /** @return int[] list of years that have at least one trip, descending */
    public function getAvailableYears($car): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.startDate')
            ->andWhere('t.car = :car')
            ->andWhere('t.startDate IS NOT NULL')
            ->setParameter('car', $car)
            ->getQuery()
            ->getArrayResult();

        $years = array_unique(array_map(
            fn($row) => (int)$row['startDate']->format('Y'),
            $rows
        ));
        rsort($years);
        return $years;
    }

    /** Total mileage and costs for completed trips, optionally filtered by year and/or user. */
    public function getTotals($car, ?int $year = null, ?int $userId = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.car = :car')
            ->andWhere('t.endMileage IS NOT NULL')
            ->setParameter('car', $car);

        if ($year !== null) {
            $qb->andWhere('t.startDate >= :yearStart')
               ->andWhere('t.startDate < :yearEnd')
               ->setParameter('yearStart', new \DateTime("$year-01-01"))
               ->setParameter('yearEnd', new \DateTime(($year + 1) . '-01-01'));
        }

        if ($userId !== null) {
            $qb->join('t.users', 'u')
               ->andWhere('u.id = :userId')
               ->setParameter('userId', $userId);
        }

        /** @var \App\Entity\Trip[] $trips */
        $trips = $qb->getQuery()->getResult();

        $mileage = array_sum(array_map(fn($t) => $t->getMileage(), $trips));
        $costs   = array_sum(array_map(fn($t) => $t->getCosts(), $trips));

        return ['mileage' => $mileage, 'costs' => $costs];
    }

    /**
     * Find the previous completed trip: the one with the highest endMileage that is
     * strictly less than the given trip's startMileage (excludes the trip itself).
     */
    public function findPreviousByMileage(Trip $trip): ?Trip
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.car = :car')
            ->andWhere('t.endMileage <= :startMileage')
            ->andWhere('t.endMileage IS NOT NULL')
            ->setParameter('car', $trip->getCar())
            ->setParameter('startMileage', $trip->getStartMileage())
            ->orderBy('t.endMileage', 'DESC')
            ->setMaxResults(1);

        if ($trip->getId() !== null) {
            $qb->andWhere('t.id != :id')->setParameter('id', $trip->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find the trip with the highest endMileage for the given car.
     */
    public function findLastByEndMileage($car): ?Trip
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.car = :car')
            ->andWhere('t.endMileage IS NOT NULL')
            ->setParameter('car', $car)
            ->orderBy('t.endMileage', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the chronologically next trip for the same car (the one whose startMileage >= this trip's endMileage).
     */
    public function findNextByMileage(Trip $trip): ?Trip
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.car = :car')
            ->andWhere('t.startMileage >= :endMileage')
            ->andWhere('t.id != :id')
            ->setParameter('car', $trip->getCar())
            ->setParameter('endMileage', $trip->getEndMileage())
            ->setParameter('id', $trip->getId())
            ->orderBy('t.startMileage', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get the last trip entered.
     */
    public function findLast($car)
    {
        return $this
            ->createQueryBuilder('t')
            ->andWhere('t.car = :val')
            ->setParameter('val', $car)
            ->orderBy('t.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
