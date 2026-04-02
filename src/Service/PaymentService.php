<?php

namespace App\Service;

use App\Entity\Payment;
use App\Message\Event\PaymentAddedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function createPayment(Payment $payment): void
    {
        $this->em->persist($payment);
        $this->em->flush();
        $this->messageBus->dispatch(new PaymentAddedEvent($payment->getId()));
    }

    public function updatePayment(Payment $payment): void
    {
        $this->em->persist($payment);
        $this->em->flush();
    }
}
