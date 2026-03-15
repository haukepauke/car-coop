<?php

namespace App\MessageHandler\Event;

use App\Message\Event\BookingDeletedEvent;
use App\Repository\CarRepository;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenBookingDeleted
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly CarRepository $carRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(BookingDeletedEvent $event): void
    {
        $this->logger->info('Processing BookingDeletedEvent', ['carId' => $event->getCarId()]);

        $car = $this->carRepository->find($event->getCarId());
        if (!$car) {
            return;
        }

        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.booking.deleted')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/booking.deleted.html.twig')
            ->context([
                'car'        => $car,
                'title'      => $event->getTitle(),
                'bookerName' => $event->getBookerName(),
                'startDate'  => $event->getStartDate(),
                'endDate'    => $event->getEndDate(),
            ]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);
    }
}
