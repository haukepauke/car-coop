<?php

namespace App\EventSubscriber;

use App\Security\SecurityAuditLogger;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApiAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SecurityAuditLogger $securityAuditLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
        ];
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if (null === $request || '/api/login' !== $request->getPathInfo()) {
            return;
        }

        $payload = json_decode($request->getContent(), true);
        $email = is_array($payload) && is_string($payload['email'] ?? null)
            ? $payload['email']
            : null;

        $this->securityAuditLogger->authenticationFailure('api_login', $email, $event->getException(), [
            'firewall' => 'api',
        ]);
    }
}
