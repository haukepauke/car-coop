<?php

namespace App\Message\Event;

class PricePerUnitChangedEvent
{
    public function __construct(
        private readonly int $userTypeId,
        private readonly float $oldPrice,
        private readonly float $newPrice,
    ) {}

    public function getUserTypeId(): int
    {
        return $this->userTypeId;
    }

    public function getOldPrice(): float
    {
        return $this->oldPrice;
    }

    public function getNewPrice(): float
    {
        return $this->newPrice;
    }
}
