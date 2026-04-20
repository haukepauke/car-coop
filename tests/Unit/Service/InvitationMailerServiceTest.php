<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Entity\Invitation;
use App\Entity\User;
use App\Entity\UserType;
use App\Repository\UserRepository;
use App\Service\InvitationMailerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvitationMailerServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private TranslatorInterface&MockObject $translator;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private UserRepository&MockObject $userRepository;
    private InvitationMailerService $service;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->service = new InvitationMailerService(
            $this->mailer,
            $this->translator,
            $this->urlGenerator,
            $this->userRepository,
            'noreply@test.local',
            'Car Coop'
        );
    }

    #[DataProvider('templateProvider')]
    public function testSendUsesExpectedTemplateBasedOnCurrentUserExistence(?User $existingUser, string $expectedTemplate): void
    {
        $invitation = $this->createInvitation();
        $sentEmail = null;

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'invitee@test.local'])
            ->willReturn($existingUser);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('invitation.email.subject', [], 'messages', 'de')
            ->willReturn('Translated subject');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'app_invite_accept',
                ['hash' => 'invite-hash'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.test/invite/accept/invite-hash');

        $this->mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (TemplatedEmail $email) use (&$sentEmail): void {
                $sentEmail = $email;
            });

        $this->service->send($invitation, 'de');

        self::assertInstanceOf(TemplatedEmail::class, $sentEmail);
        self::assertSame($expectedTemplate, $sentEmail->getHtmlTemplate());
        self::assertSame('invitee@test.local', $sentEmail->getTo()[0]->getAddress());
        self::assertSame('Translated subject', $sentEmail->getSubject());
        self::assertSame('https://example.test/invite/accept/invite-hash', $sentEmail->getContext()['acceptUrl']);
        self::assertSame('Coop Car', $sentEmail->getContext()['car']->getName());
        self::assertSame('Inviter', $sentEmail->getContext()['inviter']->getName());
    }

    public static function templateProvider(): iterable
    {
        $existingUser = new User();
        $existingUser->setEmail('invitee@test.local');
        $existingUser->setName('Existing User');
        $existingUser->setLocale('en');
        $existingUser->setPassword('hashed');

        yield 'new user invitation' => [null, 'admin/user/email/invite.html.twig'];
        yield 'existing user invitation' => [$existingUser, 'admin/user/email/invite_existing.html.twig'];
    }

    private function createInvitation(): Invitation
    {
        $car = new Car();
        $car->setName('Coop Car');
        $car->setMileage(12345);
        $car->setMilageUnit('km');
        $car->setCurrency('EUR');

        $inviter = new User();
        $inviter->setEmail('inviter@test.local');
        $inviter->setName('Inviter');
        $inviter->setLocale('en');
        $inviter->setPassword('hashed');

        $userType = new UserType();
        $userType->setName('Members');
        $userType->setCar($car);
        $userType->setPricePerUnit(0.25);

        $invitation = new Invitation();
        $invitation->setCreatedBy($inviter);
        $invitation->setUserType($userType);
        $invitation->setEmail('invitee@test.local');
        $invitation->setHash('invite-hash');
        $invitation->setStatus('new');
        $invitation->setCreatedAt(new \DateTimeImmutable());

        return $invitation;
    }
}
