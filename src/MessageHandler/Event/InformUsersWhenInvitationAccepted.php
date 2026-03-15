<?php

namespace App\MessageHandler\Event;

use App\Message\Event\InvitationAcceptedEvent;
use App\Repository\CarRepository;
use App\Repository\UserRepository;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenInvitationAccepted
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly UserRepository $userRepository,
        private readonly CarRepository $carRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(InvitationAcceptedEvent $event): void
    {
        $this->logger->info('Processing InvitationAcceptedEvent', ['userId' => $event->getUserId()]);

        $newUser = $this->userRepository->find($event->getUserId());
        $car     = $this->carRepository->find($event->getCarId());
        if (!$newUser || !$car) {
            return;
        }

        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.invitation.accepted')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/invitation.accepted.html.twig')
            ->context(['newUser' => $newUser, 'car' => $car]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);
    }
}
