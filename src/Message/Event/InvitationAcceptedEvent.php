<?php

namespace App\Message\Event;

class InvitationAcceptedEvent
{
    public function __construct(
        private readonly int $userId,
        private readonly int $carId,
    ) {
    }

    public function getUserId(): int { return $this->userId; }
    public function getCarId(): int  { return $this->carId; }
}
