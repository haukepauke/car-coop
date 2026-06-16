<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\AccessDeniedLoggingSubscriber;
use App\Security\SecurityAuditLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AccessDeniedLoggingSubscriberTest extends TestCase
{
    public function testCsrfAccessDeniedIsLoggedAsCsrfFailure(): void
    {
        $auditLogger = $this->createMock(SecurityAuditLogger::class);
        $auditLogger->expects(self::once())
            ->method('csrfFailure')
            ->with('app_logout', self::arrayHasKey('reason'));
        $auditLogger->expects(self::never())
            ->method('authorizationDenied');

        $subscriber = new AccessDeniedLoggingSubscriber($auditLogger);
        $request = Request::create('/logout', 'POST');
        $request->attributes->set('_route', 'app_logout');

        $subscriber->onKernelException(new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new AccessDeniedHttpException('Invalid CSRF token.')
        ));
    }

    public function testGenericAccessDeniedIsLoggedAsAuthorizationFailure(): void
    {
        $auditLogger = $this->createMock(SecurityAuditLogger::class);
        $auditLogger->expects(self::once())
            ->method('authorizationDenied')
            ->with('app_message_delete', self::arrayHasKey('reason'));

        $subscriber = new AccessDeniedLoggingSubscriber($auditLogger);
        $request = Request::create('/admin/messages/1/delete', 'POST');
        $request->attributes->set('_route', 'app_message_delete');

        $subscriber->onKernelException(new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new AccessDeniedHttpException('You are not allowed to delete this message.')
        ));

        self::addToAssertionCount(1);
    }
}
