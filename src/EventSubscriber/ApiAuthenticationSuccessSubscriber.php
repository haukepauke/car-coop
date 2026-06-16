<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\SecurityAuditLogger;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiAuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly RequestStack $requestStack,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
        private readonly SecurityAuditLogger $securityAuditLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $event->getUser();

        if (!$user instanceof User || null === $request || '/api/login' !== $request->getPathInfo()) {
            return;
        }

        $payload = json_decode($request->getContent(), true);
        $deviceName = is_array($payload) && is_string($payload['device_name'] ?? null)
            ? $payload['device_name']
            : null;

        $issuedRefreshToken = $this->refreshTokenService->create($user, $deviceName);
        $this->securityAuditLogger->authenticationSuccess('api_login', $user, [
            'firewall' => 'api',
            'device_name_present' => null !== $deviceName,
        ]);

        $event->setData([
            'token' => (string) ($event->getData()['token'] ?? ''),
            'refresh_token' => $issuedRefreshToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenTtl,
            'refresh_expires_in' => $this->refreshTokenTtl,
        ]);
    }
}
