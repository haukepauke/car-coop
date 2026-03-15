<?php

namespace App\Message\Event;

class InvitationCreatedEvent
{
    public function __construct(private readonly int $invitationId)
    {
    }

    public function getInvitationId(): int
    {
        return $this->invitationId;
    }
}
