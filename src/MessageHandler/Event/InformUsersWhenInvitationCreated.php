<?php

namespace App\MessageHandler\Event;

use App\Message\Event\InvitationCreatedEvent;
use App\Repository\InvitationRepository;
use App\Service\BoardMessageService;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenInvitationCreated
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly InvitationRepository $invitationRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
        private readonly BoardMessageService $boardMessageService,
    ) {
    }

    public function __invoke(InvitationCreatedEvent $event): void
    {
        $this->logger->info('Processing InvitationCreatedEvent', ['invitationId' => $event->getInvitationId()]);

        $invitation = $this->invitationRepository->find($event->getInvitationId());
        if (!$invitation) {
            return;
        }

        $car   = $invitation->getUserType()->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.invitation.created')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/invitation.created.html.twig')
            ->context(['invitation' => $invitation, 'car' => $car]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);

        $inviterName = $invitation->getCreatedBy() ? $invitation->getCreatedBy()->getName() : 'Unknown';
        $this->boardMessageService->createSystemMessage($car, 'board_system.invitation_created', [
            '%user%'  => htmlspecialchars($inviterName),
            '%email%' => htmlspecialchars($invitation->getEmail()),
            '%car%'   => htmlspecialchars($car->getName()),
        ]);
    }
}
