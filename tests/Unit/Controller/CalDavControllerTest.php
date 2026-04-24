<?php

namespace App\Tests\Unit\Controller;

use App\CalDAV\AuthBackend;
use App\CalDAV\CalendarBackend;
use App\CalDAV\PrincipalBackend;
use App\Controller\CalDavController;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CalDavControllerTest extends TestCase
{
    public function testUnexpectedErrorsReturnGeneric500WithoutInternalDetails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Unexpected CalDAV error',
                self::callback(static function (array $context): bool {
                    return isset($context['exception'], $context['method'], $context['path'])
                        && $context['exception'] instanceof \RuntimeException
                        && 'PROPFIND' === $context['method']
                        && '/caldav/' === $context['path'];
                })
            );

        $controller = new CalDavController($logger);

        $userRepository = $this->createStub(\App\Repository\UserRepository::class);
        $userRepository
            ->method('findOneBy')
            ->willThrowException(new \RuntimeException('secret failure', 0));

        $passwordHasher = $this->createStub(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $authBackend = new AuthBackend($userRepository, $passwordHasher);

        $request = Request::create('/caldav/', 'PROPFIND', server: [
            'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('user@example.com:password'),
        ]);

        $response = $controller->caldav(
            $request,
            $authBackend,
            $this->createStub(CalendarBackend::class),
            $this->createStub(PrincipalBackend::class),
        );

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getContent());
        self::assertStringNotContainsString('secret failure', $response->getContent());
        self::assertStringNotContainsString('/home/', $response->getContent());
        self::assertDoesNotMatchRegularExpression('/:\d+$/', $response->getContent());
    }
}
