<?php

namespace App\MessageHandler\Event;

use App\Message\Event\BookingAddedEvent;
use App\Repository\BookingRepository;
use App\Service\BoardMessageService;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenBookingAdded
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly BookingRepository $bookingRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
        private readonly BoardMessageService $boardMessageService,
    ) {
    }

    public function __invoke(BookingAddedEvent $event): void
    {
        $this->logger->info('Processing BookingAddedEvent', ['bookingId' => $event->getBookingId()]);

        $booking = $this->bookingRepository->find($event->getBookingId());
        if (!$booking) {
            return;
        }

        $car   = $booking->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.booking.added')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/booking.added.html.twig')
            ->context(['booking' => $booking, 'car' => $car]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);

        $userName = $booking->getUser() ? $booking->getUser()->getName() : 'Unknown';
        $start    = $booking->getStartDate() ? $booking->getStartDate()->format('Y-m-d') : '';
        $end      = $booking->getEndDate() ? $booking->getEndDate()->format('Y-m-d') : '';
        $this->boardMessageService->createSystemMessage($car, 'board_system.booking_added', [
            '%user%'  => htmlspecialchars($userName),
            '%car%'   => htmlspecialchars($car->getName()),
            '%start%' => htmlspecialchars($start),
            '%end%'   => htmlspecialchars($end),
        ]);
    }
}
