<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Car;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 *
 * @method null|Booking find($id, $lockMode = null, $lockVersion = null)
 * @method null|Booking findOneBy(array $criteria, array $orderBy = null)
 * @method Booking[]    findAll()
 * @method Booking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Booking $entity, bool $flush = true): void
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
    public function remove(Booking $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }

    public function findByCar(DateTime $startDate, DateTime $endDate, Car $car, int $limit)
    {
        return $this->createFindByCarQueryBuilder($startDate, $endDate, $car, $limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function createFindByCarQueryBuilder(DateTime $startDate, DateTime $endDate, Car $car, int $limit)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.car = :val')
            ->andWhere('b.startDate >= :startDate')
            ->andWhere('b.endDate <= :endDate')
            ->setParameter('val', $car)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.startDate', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setMaxResults($limit)
        ;
    }

    /**
     * @return Booking[]
     */
    public function findOverlappingBookings(Car $car, DateTimeInterface $startDate, DateTimeInterface $endDate, ?Booking $exclude = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.car = :car')
            ->andWhere('b.startDate < :endDate')
            ->andWhere('b.endDate > :startDate')
            ->setParameter('car', $car)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.startDate', 'ASC')
            ->addOrderBy('b.id', 'ASC');

        if ($exclude?->getId() !== null) {
            $qb
                ->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $exclude->getId());
        }

        return $qb->getQuery()->getResult();
    }
}
