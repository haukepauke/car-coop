<?php

namespace App\EventSubscriber;

use App\Security\SecurityAuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedLoggingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SecurityAuditLogger $securityAuditLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof AccessDeniedHttpException && !$throwable instanceof AccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        $action = $request->attributes->get('_route') ?? $request->getPathInfo();
        $message = trim($throwable->getMessage());
        $context = [
            'reason' => '' !== $message ? $message : $throwable::class,
        ];

        if (str_contains(strtolower($message), 'csrf')) {
            $this->securityAuditLogger->csrfFailure($action, $context);

            return;
        }

        $this->securityAuditLogger->authorizationDenied($action, $context);
    }
}
