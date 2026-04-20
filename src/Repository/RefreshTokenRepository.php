<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function add(RefreshToken $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);

        if ($flush) {
            $em->flush();
        }
    }

    public function findOneByTokenHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function revokeActiveTokensForUserDevice(User $user, string $deviceName, \DateTimeImmutable $revokedAt): void
    {
        $tokens = $this->createQueryBuilder('rt')
            ->andWhere('rt.user = :user')
            ->andWhere('rt.deviceName = :deviceName')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('deviceName', $deviceName)
            ->setParameter('now', $revokedAt)
            ->getQuery()
            ->getResult();

        foreach ($tokens as $token) {
            $token->revoke($revokedAt);
        }
    }

    public function revokeAllActiveForUser(User $user, \DateTimeImmutable $revokedAt): void
    {
        $tokens = $this->createQueryBuilder('rt')
            ->andWhere('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $revokedAt)
            ->getQuery()
            ->getResult();

        foreach ($tokens as $token) {
            $token->revoke($revokedAt);
        }
    }
}
