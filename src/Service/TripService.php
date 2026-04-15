<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\UserType;
use App\Message\Event\TripAddedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TripService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function createTrip(Trip $trip): void
    {
        $this->prepareTrip($trip);
        $this->em->persist($trip);
        $this->em->persist($trip->getCar());
        $this->em->flush();
        $this->messageBus->dispatch(new TripAddedEvent($trip->getId()));
    }

    public function updateTrip(Trip $trip): void
    {
        $this->prepareTrip($trip);
        $this->em->persist($trip);
        $this->em->persist($trip->getCar());
        $this->em->flush();
    }

    /**
     * Split trip1 at splitMileage: trip1 ends at splitMileage, trip2 covers splitMileage → original end.
     * Car mileage is unchanged (total distance driven remains the same).
     */
    public function splitTrip(Trip $trip1, int $splitMileage, Trip $trip2): void
    {
        $originalEndMileage = $trip1->getEndMileage();

        $trip1->setEndMileage($splitMileage);
        $trip1->setCosts($this->calculateTripCosts($trip1));

        $trip2->setStartMileage($splitMileage);
        $trip2->setEndMileage($originalEndMileage);
        $trip2->setCar($trip1->getCar());
        $trip2->setCosts($this->calculateTripCosts($trip2));

        $this->em->persist($trip1);
        $this->em->persist($trip2);
        $this->em->flush();
    }

    private function prepareTrip(Trip $trip): void
    {
        $trip->setCosts($this->calculateTripCosts($trip));
        $trip->getCar()->setMileage($trip->getEndMileage());
    }

    public function estimateTripCostsForUser(User $user, Car $car, int $estimatedMileage): float
    {
        return $estimatedMileage * $this->getUserTypeForCar($user, $car)->getPricePerUnit();
    }

    private function calculateTripCosts(Trip $trip): float
    {
        $user = $trip->getUsers()->first();

        $tripType = $trip->getType();
        if ('service' === $tripType || str_contains((string) $tripType, '_free')) {
            return 0.0;
        }

        $userType = $this->getUserTypeForCar($user, $trip->getCar());

        return ($trip->getEndMileage() - $trip->getStartMileage()) * $userType->getPricePerUnit();
    }

    private function getUserTypeForCar(User $user, Car $car): UserType
    {
        foreach ($user->getUserTypes() as $userType) {
            if ($userType->getCar() === $car) {
                return $userType;
            }
        }

        throw new \LogicException('User has no user group for the given car.');
    }
}
