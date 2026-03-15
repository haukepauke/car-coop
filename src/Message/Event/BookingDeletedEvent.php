<?php

namespace App\Message\Event;

class BookingDeletedEvent
{
    public function __construct(
        private readonly int $carId,
        private readonly string $title,
        private readonly string $bookerName,
        private readonly string $startDate,
        private readonly string $endDate,
    ) {
    }

    public function getCarId(): int    { return $this->carId; }
    public function getTitle(): string { return $this->title; }
    public function getBookerName(): string { return $this->bookerName; }
    public function getStartDate(): string  { return $this->startDate; }
    public function getEndDate(): string    { return $this->endDate; }
}
