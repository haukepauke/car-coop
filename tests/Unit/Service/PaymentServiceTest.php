<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Entity\Payment;
use App\Entity\User;
use App\Message\Event\PaymentAddedEvent;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PaymentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $messageBus;
    private PaymentService $service;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->messageBus->method('dispatch')->willReturnCallback(
            fn(object $message) => new Envelope($message)
        );

        $this->service = new PaymentService($this->em, $this->messageBus);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setId(object $entity, int $id): void
    {
        $prop = new \ReflectionProperty($entity, 'id');
        $prop->setValue($entity, $id);
    }

    private function makePayment(): Payment
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(0);
        $car->setMilageUnit('km');

        $fromUser = new User();
        $fromUser->setEmail('payer@test.com');
        $fromUser->setName('Payer');
        $fromUser->setLocale('en');
        $fromUser->setPassword('hashed');

        $toUser = new User();
        $toUser->setEmail('receiver@test.com');
        $toUser->setName('Receiver');
        $toUser->setLocale('en');
        $toUser->setPassword('hashed');

        $payment = new Payment();
        $payment->setCar($car);
        $payment->setFromUser($fromUser);
        $payment->setToUser($toUser);
        $payment->setAmount(50.0);
        $payment->setDate(new \DateTime('2024-06-01'));
        $payment->setType('banktransfer');

        return $payment;
    }

    // ── createPayment() ───────────────────────────────────────────────────────

    public function testCreatePaymentPersistsAndFlushes(): void
    {
        $payment = $this->makePayment();
        $this->setId($payment, 3);

        $this->em->expects($this->once())->method('persist')->with($payment);
        $this->em->expects($this->once())->method('flush');

        $this->service->createPayment($payment);
    }

    public function testCreatePaymentDispatchesPaymentAddedEvent(): void
    {
        $payment = $this->makePayment();
        $this->setId($payment, 3);

        $dispatched = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched) {
                $dispatched = $message;
                return new Envelope($message);
            });

        $this->service->createPayment($payment);

        $this->assertInstanceOf(PaymentAddedEvent::class, $dispatched);
        $this->assertSame(3, $dispatched->getPaymentId());
    }

    // ── updatePayment() ───────────────────────────────────────────────────────

    public function testUpdatePaymentPersistsAndFlushes(): void
    {
        $payment = $this->makePayment();

        $this->em->expects($this->once())->method('persist')->with($payment);
        $this->em->expects($this->once())->method('flush');

        $this->service->updatePayment($payment);
    }

    public function testUpdatePaymentDoesNotDispatchEvent(): void
    {
        $payment = $this->makePayment();

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->updatePayment($payment);
    }
}
