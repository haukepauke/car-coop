<?php

namespace App\Message\Event;

class BookingAddedEvent
{
    public function __construct(private readonly int $bookingId)
    {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }
}
