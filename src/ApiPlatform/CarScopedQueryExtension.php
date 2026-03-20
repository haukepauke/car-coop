<?php

namespace App\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Booking;
use App\Entity\Car;
use App\Entity\Expense;
use App\Entity\Message;
use App\Entity\Payment;
use App\Entity\ParkingLocation;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restricts API collection queries to records belonging to cars
 * the authenticated user is a member of.
 */
class CarScopedQueryExtension implements QueryCollectionExtensionInterface
{
    private const CAR_RELATED = [
        Trip::class,
        Booking::class,
        Expense::class,
        Payment::class,
        ParkingLocation::class,
        Message::class,
    ];

    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $carIds = array_map(fn(Car $car) => $car->getId(), $user->getCars());
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Cars — only show cars the user belongs to
        if ($resourceClass === Car::class) {
            if (empty($carIds)) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }
            $queryBuilder
                ->andWhere("{$rootAlias}.id IN (:car_scope_ids)")
                ->setParameter('car_scope_ids', $carIds);
            return;
        }

        // Users — only show users who share at least one car with the current user
        if ($resourceClass === User::class) {
            if (empty($carIds)) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }
            $queryBuilder
                ->join("{$rootAlias}.userTypes", '_ut_scope')
                ->join('_ut_scope.car', '_user_car_scope')
                ->andWhere('_user_car_scope.id IN (:car_scope_ids)')
                ->setParameter('car_scope_ids', $carIds)
                ->distinct();
            return;
        }

        // Trip, Booking, Expense, Payment, ParkingLocation — filter by car
        if (in_array($resourceClass, self::CAR_RELATED, true)) {
            if (empty($carIds)) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }
            $queryBuilder
                ->join("{$rootAlias}.car", '_car_scope')
                ->andWhere('_car_scope.id IN (:car_scope_ids)')
                ->setParameter('car_scope_ids', $carIds);
        }
    }
}
