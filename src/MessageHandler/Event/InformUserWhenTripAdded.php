<?php

namespace App\MessageHandler\Event;

use App\Message\Event\TripAddedEvent;
use App\Repository\TripRepository;
use App\Service\EventMailerService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUserWhenTripAdded
{
    private EventMailerService $mailer;
    private TripRepository $tr;
    private string $mailerFromEmail;
    private string $mailerFromName;

    public function __construct(EventMailerService $mailer, TripRepository $tr, string $mailerFromEmail, string $mailerFromName)
    {
        $this->mailer = $mailer;
        $this->tr = $tr;
        $this->mailerFromEmail = $mailerFromEmail;
        $this->mailerFromName = $mailerFromName;
    }

    public function __invoke(TripAddedEvent $event)
    {
        $trip = $this->tr->find($event->getTripId());
        $car = $trip->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('email.trip.added')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/trip.added.html.twig')
            ->context([
                'trip' => $trip,
                'car' => $car,
            ])
        ;
        $this->mailer->sendMails($users, $email);
    }
}
