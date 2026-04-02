<?php

namespace App\Service;

use App\Entity\Booking;
use App\Message\Event\BookingAddedEvent;
use App\Message\Event\BookingDeletedEvent;
use App\Message\Event\BookingUpdatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function createBooking(Booking $booking): void
    {
        $this->em->persist($booking);
        $this->em->flush();
        $this->messageBus->dispatch(new BookingAddedEvent($booking->getId()));
    }

    public function updateBooking(Booking $booking): void
    {
        $this->em->persist($booking);
        $this->em->flush();
        $this->messageBus->dispatch(new BookingUpdatedEvent($booking->getId()));
    }

    public function deleteBooking(Booking $booking): void
    {
        $event = new BookingDeletedEvent(
            $booking->getCar()->getId(),
            (string) $booking->getTitle(),
            $booking->getUser()->getName(),
            $booking->getStartDate()->format('Y-m-d H:i'),
            $booking->getEndDate()->format('Y-m-d H:i'),
        );

        $this->em->remove($booking);
        $this->em->flush();
        $this->messageBus->dispatch($event);
    }
}
