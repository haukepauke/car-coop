<?php

namespace App\MessageHandler\Event;

use App\Message\Event\TripAddedEvent;
use App\Repository\TripRepository;
use App\Service\BoardMessageService;
use App\Service\EventMailerService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUserWhenTripAdded
{
    private EventMailerService $mailer;
    private TripRepository $tr;
    private string $mailerFromEmail;
    private string $mailerFromName;
    private LoggerInterface $logger;
    private BoardMessageService $boardMessageService;

    public function __construct(EventMailerService $mailer, TripRepository $tr, string $mailerFromEmail, string $mailerFromName, LoggerInterface $logger, BoardMessageService $boardMessageService)
    {
        $this->mailer = $mailer;
        $this->tr = $tr;
        $this->mailerFromEmail = $mailerFromEmail;
        $this->mailerFromName = $mailerFromName;
        $this->logger = $logger;
        $this->boardMessageService = $boardMessageService;
    }

    public function __invoke(TripAddedEvent $event)
    {
        $this->logger->info('Processing TripAddedEvent', ['tripId' => $event->getTripId()]);
        $trip = $this->tr->find($event->getTripId());
        $car = $trip->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.trip.added')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/trip.added.html.twig')
            ->context([
                'trip' => $trip,
                'car' => $car,
            ])
        ;
        $this->mailer->sendMails($users, $email);

        $editorName = $trip->getEditor() ? $trip->getEditor()->getName() : 'Unknown';
        $this->boardMessageService->createSystemMessage($car, 'board_system.trip_added', [
            '%user%' => htmlspecialchars($editorName),
            '%car%'  => htmlspecialchars($car->getName()),
        ]);
    }
}
