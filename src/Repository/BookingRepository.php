<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Car;
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
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(Booking $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function findByCar(Car $car, int $limit)
    {
        return $this->createFindByCarQueryBuilder($car, $limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function createFindByCarQueryBuilder(Car $car, int $limit)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.car = :val')
            ->setParameter('val', $car)
            ->orderBy('b.startDate', 'ASC')
            ->setMaxResults($limit)
        ;
    }
}
