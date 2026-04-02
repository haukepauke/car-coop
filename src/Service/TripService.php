<?php

namespace App\Service;

use App\Entity\Trip;
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

    private function prepareTrip(Trip $trip): void
    {
        $trip->setCosts($this->calculateTripCosts($trip));
        if ($trip->isCompleted()) {
            $trip->getCar()->setMileage($trip->getEndMileage());
        }
    }

    private function calculateTripCosts(Trip $trip): float
    {
        $user = $trip->getUsers()->first();
        $userType = $user->getUserTypes()->get(0);

        if ('service' === $trip->getType()) {
            return 0.0;
        }

        return ($trip->getEndMileage() - $trip->getStartMileage()) * $userType->getPricePerUnit();
    }
}
