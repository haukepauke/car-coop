<?php

namespace App\Message\Event;

class PaymentAddedEvent
{
    public function __construct(private readonly int $paymentId)
    {
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }
}
