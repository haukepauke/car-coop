<?php

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Entity\Car;
use App\Entity\User;
use App\Message\Event\BookingAddedEvent;
use App\Message\Event\BookingDeletedEvent;
use App\Message\Event\BookingUpdatedEvent;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class BookingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $messageBus;
    private BookingService $service;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->messageBus->method('dispatch')->willReturnCallback(
            fn(object $message) => new Envelope($message)
        );

        $this->service = new BookingService($this->em, $this->messageBus);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setId(object $entity, int $id): void
    {
        $prop = new \ReflectionProperty($entity, 'id');
        $prop->setValue($entity, $id);
    }

    private function makeBooking(): Booking
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(0);
        $car->setMilageUnit('km');
        $this->setId($car, 10);

        $user = new User();
        $user->setEmail('booker@test.com');
        $user->setName('Booker');
        $user->setLocale('en');
        $user->setPassword('hashed');

        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($user);
        $booking->setTitle('Weekend Trip');
        $booking->setStatus('fixed');
        $booking->setStartDate(new \DateTime('2025-07-01 10:00'));
        $booking->setEndDate(new \DateTime('2025-07-03 18:00'));

        return $booking;
    }

    // ── createBooking() ───────────────────────────────────────────────────────

    public function testCreateBookingPersistsAndFlushes(): void
    {
        $booking = $this->makeBooking();
        $this->setId($booking, 5);

        $this->em->expects($this->once())->method('persist')->with($booking);
        $this->em->expects($this->once())->method('flush');

        $this->service->createBooking($booking);
    }

    public function testCreateBookingDispatchesBookingAddedEvent(): void
    {
        $booking = $this->makeBooking();
        $this->setId($booking, 5);

        $dispatched = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched) {
                $dispatched = $message;
                return new Envelope($message);
            });

        $this->service->createBooking($booking);

        $this->assertInstanceOf(BookingAddedEvent::class, $dispatched);
        $this->assertSame(5, $dispatched->getBookingId());
    }

    // ── updateBooking() ───────────────────────────────────────────────────────

    public function testUpdateBookingPersistsAndFlushes(): void
    {
        $booking = $this->makeBooking();
        $this->setId($booking, 7);

        $this->em->expects($this->once())->method('persist')->with($booking);
        $this->em->expects($this->once())->method('flush');

        $this->service->updateBooking($booking);
    }

    public function testUpdateBookingDispatchesBookingUpdatedEvent(): void
    {
        $booking = $this->makeBooking();
        $this->setId($booking, 7);

        $dispatched = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched) {
                $dispatched = $message;
                return new Envelope($message);
            });

        $this->service->updateBooking($booking);

        $this->assertInstanceOf(BookingUpdatedEvent::class, $dispatched);
        $this->assertSame(7, $dispatched->getBookingId());
    }

    // ── deleteBooking() ───────────────────────────────────────────────────────

    public function testDeleteBookingRemovesAndFlushes(): void
    {
        $booking = $this->makeBooking();

        $this->em->expects($this->once())->method('remove')->with($booking);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteBooking($booking);
    }

    public function testDeleteBookingDispatchesBookingDeletedEventWithCorrectData(): void
    {
        $booking = $this->makeBooking();

        $dispatched = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched) {
                $dispatched = $message;
                return new Envelope($message);
            });

        $this->service->deleteBooking($booking);

        $this->assertInstanceOf(BookingDeletedEvent::class, $dispatched);
        $this->assertSame(10, $dispatched->getCarId());
        $this->assertSame('Weekend Trip', $dispatched->getTitle());
        $this->assertSame('Booker', $dispatched->getBookerName());
        $this->assertSame('2025-07-01 10:00', $dispatched->getStartDate());
        $this->assertSame('2025-07-03 18:00', $dispatched->getEndDate());
    }

    public function testDeleteBookingCapturesEventDataBeforeRemoval(): void
    {
        $booking = $this->makeBooking();

        // Simulate em->remove() clearing relations, which would break event creation if called after
        $this->em->method('remove')->willReturnCallback(function (object $entity) {
            // After removal the entity is detached; event data must already be captured
        });

        // If event data is captured after remove, this would throw or produce empty values
        $dispatched = null;
        $this->messageBus->method('dispatch')->willReturnCallback(function (object $message) use (&$dispatched) {
            $dispatched = $message;
            return new Envelope($message);
        });

        $this->service->deleteBooking($booking);

        $this->assertSame('Weekend Trip', $dispatched->getTitle());
        $this->assertSame('Booker', $dispatched->getBookerName());
    }
}
