<?php

namespace App\Message\Event;

class SuperAdminBroadcastEvent
{
    public function __construct(
        private readonly string $subject,
        private readonly string $content,
        private readonly int $authorId,
    ) {}

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }
}
