<?php

namespace App\MessageHandler\Event;

use App\Message\Event\TripAddedEvent;
use App\Repository\TripRepository;
use App\Service\EventMailerService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Address;

class InformUserWhenTripAdded implements MessageHandlerInterface
{
    private EventMailerService $mailer;
    private TripRepository $tr;

    public function __construct(EventMailerService $mailer, TripRepository $tr)
    {
        $this->mailer = $mailer;
        $this->tr = $tr;
    }

    public function __invoke(TripAddedEvent $event)
    {
        $trip = $this->tr->find($event->getTripId());
        $car = $trip->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('email.trip.added')
            ->from(new Address('webmaster@car-coop.net', 'Car Coop Mail Bot'))
            ->htmlTemplate('event/email/trip.added.html.twig')
            ->context([
                'trip' => $trip,
                'car' => $car,
            ])
        ;
        $this->mailer->sendMails($users, $email);
    }
}
