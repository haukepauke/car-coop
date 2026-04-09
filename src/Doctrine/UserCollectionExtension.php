<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restricts GET /api/users collection to users who share at least one car
 * with the currently authenticated user.
 */
class UserCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== User::class) {
            return;
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if (null === $currentUser) {
            // Should not happen (security already requires ROLE_USER), but be safe
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Join through userTypes → car → userTypes → users to find carmates
        $queryBuilder
            ->innerJoin("{$rootAlias}.userTypes", 'ut')
            ->innerJoin('ut.car', 'c')
            ->innerJoin('c.userTypes', 'ut2')
            ->innerJoin('ut2.users', 'carmate')
            ->andWhere('carmate = :currentUser')
            ->setParameter('currentUser', $currentUser);
    }
}
