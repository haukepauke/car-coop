<?php

namespace App\Message\Event;

class MilestoneReachedEvent
{
    public function __construct(
        private readonly int $tripId,
        private readonly int $milestone,
        private readonly string $type,
        private readonly string $boardKey,
    ) {}

    public function getTripId(): int
    {
        return $this->tripId;
    }

    public function getMilestone(): int
    {
        return $this->milestone;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getBoardKey(): string
    {
        return $this->boardKey;
    }
}
