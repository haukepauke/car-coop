<?php

namespace App\Security;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityAuditLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function authenticationSuccess(string $mechanism, ?UserInterface $user = null, array $context = []): void
    {
        $this->logger->notice('security.authentication_success', $this->buildContext($context + [
            'mechanism' => $mechanism,
            'outcome' => 'success',
        ], $user));
    }

    public function authenticationFailure(
        string $mechanism,
        ?string $identifier = null,
        ?\Throwable $exception = null,
        array $context = []
    ): void {
        $baseContext = [
            'mechanism' => $mechanism,
            'outcome' => 'failure',
        ];

        if (null !== $identifier && '' !== trim($identifier)) {
            $baseContext['identifier_hash'] = $this->hashValue($identifier);
        }

        if (null !== $exception) {
            $baseContext['reason'] = $exception::class;
        }

        $this->logger->warning('security.authentication_failure', $this->buildContext($context + $baseContext));
    }

    public function refreshTokenSuccess(UserInterface $user, ?string $refreshToken = null, array $context = []): void
    {
        if (null !== $refreshToken && '' !== trim($refreshToken)) {
            $context['refresh_token_fingerprint'] = $this->fingerprint($refreshToken);
        }

        $this->logger->notice('security.refresh_token_success', $this->buildContext($context + [
            'outcome' => 'success',
        ], $user));
    }

    public function refreshTokenFailure(string $reason, ?string $refreshToken = null, array $context = []): void
    {
        if (null !== $refreshToken && '' !== trim($refreshToken)) {
            $context['refresh_token_fingerprint'] = $this->fingerprint($refreshToken);
        }

        $this->logger->warning('security.refresh_token_failure', $this->buildContext($context + [
            'outcome' => 'failure',
            'reason' => $reason,
        ]));
    }

    public function logoutSuccess(string $mechanism, ?UserInterface $user = null, array $context = []): void
    {
        $this->logger->notice('security.logout_success', $this->buildContext($context + [
            'mechanism' => $mechanism,
            'outcome' => 'success',
        ], $user));
    }

    public function logoutFailure(string $mechanism, string $reason, array $context = []): void
    {
        $this->logger->warning('security.logout_failure', $this->buildContext($context + [
            'mechanism' => $mechanism,
            'outcome' => 'failure',
            'reason' => $reason,
        ]));
    }

    public function csrfFailure(string $action, array $context = []): void
    {
        $this->logger->warning('security.csrf_failure', $this->buildContext($context + [
            'action' => $action,
            'outcome' => 'failure',
        ]));
    }

    public function authorizationDenied(string $action, array $context = []): void
    {
        $this->logger->warning('security.authorization_denied', $this->buildContext($context + [
            'action' => $action,
            'outcome' => 'denied',
        ]));
    }

    private function buildContext(array $context, ?UserInterface $user = null): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $resolvedUser = $user ?? $this->security->getUser();

        $baseContext = [
            'route' => $request?->attributes->get('_route'),
            'path' => $request?->getPathInfo(),
            'method' => $request?->getMethod(),
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
        ];

        if ($resolvedUser instanceof UserInterface) {
            $baseContext['user_id'] = $resolvedUser instanceof User ? $resolvedUser->getId() : null;
            $baseContext['user_identifier_hash'] = $this->hashValue($resolvedUser->getUserIdentifier());
        }

        return $this->sanitizeContext(array_filter($context + $baseContext, static fn ($value): bool => null !== $value));
    }

    private function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $context[$key] = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitizeContext($value);
            }
        }

        return $context;
    }

    private function hashValue(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value)));
    }

    private function fingerprint(string $value): string
    {
        return substr(hash('sha256', trim($value)), 0, 16);
    }
}
