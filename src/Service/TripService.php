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
        $this->applyTripCosts($trip1);

        $trip2->setStartMileage($splitMileage);
        $trip2->setEndMileage($originalEndMileage);
        $trip2->setCar($trip1->getCar());
        $this->applyTripCosts($trip2);

        $this->em->persist($trip1);
        $this->em->persist($trip2);
        $this->em->flush();
    }

    private function prepareTrip(Trip $trip): void
    {
        $this->applyTripCosts($trip);
        $trip->getCar()->setMileage($trip->getEndMileage());
    }

    public function estimateTripCostsForUser(User $user, Car $car, int $estimatedMileage): float
    {
        return $estimatedMileage * $this->getUserTypeForCar($user, $car)->getPricePerUnit();
    }

    private function applyTripCosts(Trip $trip): void
    {
        $tripCostData = $this->calculateTripCostData($trip);

        $trip->setCosts($tripCostData['costs']);
        $trip->setCostShares($tripCostData['costShares']);
    }

    /**
     * Calculate trip costs as immutable historical values.
     *
     * A paid trip's mileage is split evenly between all assigned users. Each
     * user's share is priced with that user's group price for this car at the
     * moment the trip is recorded or updated. The per-user shares are persisted
     * on the trip so later group price changes do not alter old balances.
     *
     * @return array{costs: float, costShares: array<string, float>}
     */
    private function calculateTripCostData(Trip $trip): array
    {
        $tripType = $trip->getType();
        if ('service' === $tripType || str_contains((string) $tripType, '_free')) {
            return [
                'costs' => 0.0,
                'costShares' => [],
            ];
        }

        $users = $trip->getUsers();
        $userCount = $users->count();
        if (0 === $userCount) {
            throw new \LogicException('Trip has no users.');
        }

        $mileageShare = $trip->getMileage() / $userCount;
        $costs = 0.0;
        $costShares = [];

        foreach ($users as $user) {
            $userType = $this->getUserTypeForCar($user, $trip->getCar());
            $costShare = $mileageShare * $userType->getPricePerUnit();
            $costs += $costShare;

            if ($user->getId() !== null) {
                $costShares[(string) $user->getId()] = $costShare;
            }
        }

        return [
            'costs' => $costs,
            'costShares' => $costShares,
        ];
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
