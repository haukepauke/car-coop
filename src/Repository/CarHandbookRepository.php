<?php

namespace App\Repository;

use App\Entity\CarHandbook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CarHandbook>
 *
 * @method CarHandbook|null find($id, $lockMode = null, $lockVersion = null)
 * @method CarHandbook|null findOneBy(array $criteria, array $orderBy = null)
 * @method CarHandbook[]    findAll()
 * @method CarHandbook[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CarHandbookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarHandbook::class);
    }
}
