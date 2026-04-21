<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\ValueObject\IssuedRefreshToken;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $kernelSecret,
        private readonly int $refreshTokenTtl,
    ) {
    }

    public function create(User $user, ?string $deviceName = null): IssuedRefreshToken
    {
        $now = new \DateTimeImmutable();
        $deviceName = $this->normalizeDeviceName($deviceName);

        if (null !== $deviceName) {
            $this->refreshTokenRepository->revokeActiveTokensForUserDevice($user, $deviceName, $now);
        }

        $issuedToken = $this->issueToken($user, $now, $deviceName);
        $this->entityManager->flush();

        return $issuedToken;
    }

    public function refresh(string $plainTextToken): ?IssuedRefreshToken
    {
        $now = new \DateTimeImmutable();
        $refreshToken = $this->findByPlainTextToken($plainTextToken);

        if (null === $refreshToken || !$refreshToken->isActiveAt($now)) {
            return null;
        }

        $refreshToken->markUsed($now);
        $refreshToken->revoke($now);

        $issuedToken = $this->issueToken($refreshToken->getUser(), $now, $refreshToken->getDeviceName());
        $this->entityManager->flush();

        return $issuedToken;
    }

    public function revoke(string $plainTextToken, ?User $user = null): bool
    {
        $refreshToken = $this->findByPlainTextToken($plainTextToken);
        $now = new \DateTimeImmutable();

        if (null === $refreshToken || !$refreshToken->isActiveAt($now)) {
            return false;
        }

        if (null !== $user && $refreshToken->getUser()->getId() !== $user->getId()) {
            return false;
        }

        $refreshToken->revoke($now);
        $this->entityManager->flush();

        return true;
    }

    public function revokeAllForUser(User $user): void
    {
        $this->refreshTokenRepository->revokeAllActiveForUser($user, new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function issueToken(User $user, \DateTimeImmutable $now, ?string $deviceName): IssuedRefreshToken
    {
        $plainTextToken = $this->generatePlainTextToken();
        $refreshToken = new RefreshToken(
            $user,
            $this->hashToken($plainTextToken),
            $now,
            $now->modify(sprintf('+%d seconds', $this->refreshTokenTtl)),
            $deviceName,
        );

        $this->entityManager->persist($refreshToken);

        return new IssuedRefreshToken($plainTextToken, $refreshToken);
    }

    private function findByPlainTextToken(string $plainTextToken): ?RefreshToken
    {
        $plainTextToken = trim($plainTextToken);
        if ('' === $plainTextToken) {
            return null;
        }

        return $this->refreshTokenRepository->findOneByTokenHash(
            $this->hashToken($plainTextToken)
        );
    }

    private function generatePlainTextToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, $this->kernelSecret);
    }

    private function normalizeDeviceName(?string $deviceName): ?string
    {
        if (null === $deviceName) {
            return null;
        }

        $deviceName = trim($deviceName);
        if ('' === $deviceName) {
            return null;
        }

        return mb_substr($deviceName, 0, 255);
    }
}
