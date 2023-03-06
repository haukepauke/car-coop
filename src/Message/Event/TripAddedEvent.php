<?php

namespace App\Message\Event;

class TripAddedEvent
{
    private $tripId;

    public function __construct(int $tripId)
    {
        $this->tripId = $tripId;
    }

    public function getTripId(): int
    {
        return $this->tripId;
    }
}
