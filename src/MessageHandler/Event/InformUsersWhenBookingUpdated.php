<?php

namespace App\MessageHandler\Event;

use App\Message\Event\BookingUpdatedEvent;
use App\Repository\BookingRepository;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenBookingUpdated
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly BookingRepository $bookingRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(BookingUpdatedEvent $event): void
    {
        $this->logger->info('Processing BookingUpdatedEvent', ['bookingId' => $event->getBookingId()]);

        $booking = $this->bookingRepository->find($event->getBookingId());
        if (!$booking) {
            return;
        }

        $car   = $booking->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.booking.updated')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/booking.updated.html.twig')
            ->context(['booking' => $booking, 'car' => $car]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);
    }
}
