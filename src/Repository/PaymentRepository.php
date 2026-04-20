<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 *
 * @method null|Payment find($id, $lockMode = null, $lockVersion = null)
 * @method null|Payment findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Payment $entity, bool $flush = true): void
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
    public function remove(Payment $entity, bool $flush = true): void
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

    public function createFindByCarQueryBuilder($car, ?int $year = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.car = :val')
            ->setParameter('val', $car)
            ->orderBy('p.date', 'DESC');

        if ($year !== null) {
            $qb->andWhere('p.date >= :yearStart')
               ->andWhere('p.date < :yearEnd')
               ->setParameter('yearStart', new \DateTime("$year-01-01"))
               ->setParameter('yearEnd', new \DateTime(($year + 1) . '-01-01'));
        }

        return $qb;
    }

    /** @return int[] list of years that have at least one payment, descending */
    public function getAvailableYears($car): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.date')
            ->andWhere('p.car = :car')
            ->setParameter('car', $car)
            ->getQuery()
            ->getArrayResult();

        $years = array_unique(array_map(
            fn($row) => (int)$row['date']->format('Y'),
            $rows
        ));
        rsort($years);
        return $years;
    }

    public function getTotal($car, ?int $year = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->andWhere('p.car = :car')
            ->setParameter('car', $car);

        if ($year !== null) {
            $qb->andWhere('p.date >= :yearStart')
               ->andWhere('p.date < :yearEnd')
               ->setParameter('yearStart', new \DateTime("$year-01-01"))
               ->setParameter('yearEnd', new \DateTime(($year + 1) . '-01-01'));
        }

        return (float) ($qb->getQuery()->getSingleScalarResult() ?? 0);
    }
}
