<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\UserType;
use App\Message\Event\TripAddedEvent;
use App\Service\TripService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TripServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $messageBus;
    private TripService $service;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->messageBus->method('dispatch')->willReturnCallback(
            fn(object $message) => new Envelope($message)
        );

        $this->service = new TripService($this->em, $this->messageBus);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setId(object $entity, int $id): void
    {
        $prop = new \ReflectionProperty($entity, 'id');
        $prop->setValue($entity, $id);
    }

    private function makeCompletedTrip(int $start = 10000, int $end = 10300, float $pricePerUnit = 0.30): Trip
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage($start);
        $car->setMilageUnit('km');

        $userType = new UserType();
        $userType->setPricePerUnit($pricePerUnit);
        $userType->setName('Members');
        $userType->setCar($car);
        $userType->setActive(true);
        $userType->setAdmin(false);
        $userType->setOccasionalUse(false);

        $user = new User();
        $user->setEmail('driver@test.com');
        $user->setName('Driver');
        $user->setLocale('en');
        $user->setPassword('hashed');
        $user->addUserType($userType);

        $trip = new Trip();
        $trip->setCar($car);
        $trip->setType('vacation');
        $trip->setStartMileage($start);
        $trip->setEndMileage($end);
        $trip->setStartDate(new \DateTime('2024-06-01'));
        $trip->setEndDate(new \DateTime('2024-06-05'));
        $trip->addUser($user);

        return $trip;
    }

    private function makeUserForCar(Car $car, float $pricePerUnit): User
    {
        $userType = new UserType();
        $userType->setPricePerUnit($pricePerUnit);
        $userType->setName('Members');
        $userType->setCar($car);
        $userType->setActive(true);
        $userType->setAdmin(false);
        $userType->setOccasionalUse(false);

        $user = new User();
        $user->setEmail('driver@test.com');
        $user->setName('Driver');
        $user->setLocale('en');
        $user->setPassword('hashed');
        $user->addUserType($userType);

        return $user;
    }

    private function makeServiceTrip(): Trip
    {
        $trip = $this->makeCompletedTrip();
        $trip->setType('service');
        return $trip;
    }

    private function makeFreeTrip(string $type = 'service_free'): Trip
    {
        $trip = $this->makeCompletedTrip();
        $trip->setType($type);
        return $trip;
    }

    // ── createTrip() ──────────────────────────────────────────────────────────

    public function testCreateTripCalculatesCosts(): void
    {
        $trip = $this->makeCompletedTrip(10000, 10300, 0.30); // 300 * 0.30 = 90.0
        $this->setId($trip, 42);

        $this->service->createTrip($trip);

        $this->assertSame(90.0, $trip->getCosts());
    }

    public function testCreateTripSetsCostsToZeroForServiceTrip(): void
    {
        $trip = $this->makeServiceTrip();
        $this->setId($trip, 1);

        $this->service->createTrip($trip);

        $this->assertSame(0.0, $trip->getCosts());
    }

    public function testCreateTripSetsCostsToZeroForFreeTripType(): void
    {
        $trip = $this->makeFreeTrip('other_free');
        $this->setId($trip, 1);

        $this->service->createTrip($trip);

        $this->assertSame(0.0, $trip->getCosts());
    }

    public function testCreateTripUpdatesCarMileageWhenCompleted(): void
    {
        $trip = $this->makeCompletedTrip(10000, 10300);
        $this->setId($trip, 1);

        $this->service->createTrip($trip);

        $this->assertSame(10300, $trip->getCar()->getMileage());
    }

public function testCreateTripPersistsTripAndCar(): void
    {
        $trip = $this->makeCompletedTrip();
        $this->setId($trip, 1);

        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) {
                $this->assertContains(get_class($entity), [Trip::class, Car::class]);
            });
        $this->em->expects($this->once())->method('flush');

        $this->service->createTrip($trip);
    }

    public function testCreateTripDispatchesTripAddedEvent(): void
    {
        $trip = $this->makeCompletedTrip();
        $this->setId($trip, 99);

        $dispatched = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched) {
                $dispatched = $message;
                return new Envelope($message);
            });

        $this->service->createTrip($trip);

        $this->assertInstanceOf(TripAddedEvent::class, $dispatched);
        assert($dispatched instanceof TripAddedEvent);
        $this->assertSame(99, $dispatched->getTripId());
    }

    // ── updateTrip() ──────────────────────────────────────────────────────────

    public function testUpdateTripCalculatesCosts(): void
    {
        $trip = $this->makeCompletedTrip(10000, 10250, 0.40); // 250 * 0.40 = 100.0

        $this->service->updateTrip($trip);

        $this->assertSame(100.0, $trip->getCosts());
    }

    public function testUpdateTripSetsCostsToZeroForServiceTrip(): void
    {
        $trip = $this->makeServiceTrip();

        $this->service->updateTrip($trip);

        $this->assertSame(0.0, $trip->getCosts());
    }

    public function testUpdateTripSetsCostsToZeroForFreeTripType(): void
    {
        $trip = $this->makeFreeTrip('placeholder_free');

        $this->service->updateTrip($trip);

        $this->assertSame(0.0, $trip->getCosts());
    }

    public function testUpdateTripUpdatesCarMileageWhenCompleted(): void
    {
        $trip = $this->makeCompletedTrip(10000, 10250);

        $this->service->updateTrip($trip);

        $this->assertSame(10250, $trip->getCar()->getMileage());
    }

public function testUpdateTripPersistsTripAndCar(): void
    {
        $trip = $this->makeCompletedTrip();

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->updateTrip($trip);
    }

    public function testUpdateTripDoesNotDispatchEvent(): void
    {
        $trip = $this->makeCompletedTrip();

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->updateTrip($trip);
    }

    public function testEstimateTripCostsUsesUserGroupPriceForCar(): void
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(10000);
        $car->setMilageUnit('km');

        $otherCar = new Car();
        $otherCar->setName('Other Car');
        $otherCar->setMileage(5000);
        $otherCar->setMilageUnit('km');

        $user = $this->makeUserForCar($car, 0.35);
        $user->addUserType((new UserType())
            ->setPricePerUnit(0.99)
            ->setName('Other')
            ->setCar($otherCar)
            ->setActive(true)
            ->setAdmin(false)
            ->setOccasionalUse(false));

        $this->assertSame(87.5, $this->service->estimateTripCostsForUser($user, $car, 250));
    }
}
