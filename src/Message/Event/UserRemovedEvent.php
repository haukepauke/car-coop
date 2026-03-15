<?php

namespace App\Message\Event;

class UserRemovedEvent
{
    public function __construct(
        private readonly int $carId,
        private readonly string $userName,
    ) {
    }

    public function getCarId(): int      { return $this->carId; }
    public function getUserName(): string { return $this->userName; }
}
