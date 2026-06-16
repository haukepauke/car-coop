<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\SecurityAuditLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SecurityAuditLoggerTest extends TestCase
{
    public function testAuthenticationFailureHashesIdentifierAndAddsRequestContext(): void
    {
        $request = Request::create('/api/login', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => "Bad\r\nAgent",
        ]);
        $request->attributes->set('_route', 'api_login');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $logger = new InMemoryLogger();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $auditLogger = new SecurityAuditLogger($logger, $requestStack, $security);
        $auditLogger->authenticationFailure('api_login', "User@example.com\r\n", new \RuntimeException('nope'));

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('security.authentication_failure', $logger->records[0]['message']);
        self::assertSame('api_login', $logger->records[0]['context']['route']);
        self::assertSame('POST', $logger->records[0]['context']['method']);
        self::assertSame('127.0.0.1', $logger->records[0]['context']['ip']);
        self::assertSame('Bad Agent', $logger->records[0]['context']['user_agent']);
        self::assertSame(hash('sha256', 'user@example.com'), $logger->records[0]['context']['identifier_hash']);
    }

    public function testAuthorizationDeniedUsesCurrentAuthenticatedUserWhenAvailable(): void
    {
        $request = Request::create('/admin/messages/1/delete', 'POST');
        $request->attributes->set('_route', 'app_message_delete');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $user = new User();
        $user->setEmail('audit@test.local');
        $user->setName('Audit User');
        $user->setLocale('en');
        $user->setPassword('hashed');

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, 42);

        $logger = new InMemoryLogger();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $auditLogger = new SecurityAuditLogger($logger, $requestStack, $security);
        $auditLogger->authorizationDenied('app_message_delete', ['reason' => 'forbidden']);

        self::assertSame(42, $logger->records[0]['context']['user_id']);
        self::assertSame(hash('sha256', 'audit@test.local'), $logger->records[0]['context']['user_identifier_hash']);
    }
}

class InMemoryLogger implements LoggerInterface
{
    public array $records = [];

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
