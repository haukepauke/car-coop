<?php

namespace App\Service;

use App\Entity\Trip;

class TripCostCalculatorService
{
    public function calculateTripCosts(Trip $trip): float
    {
        $user = $trip->getUsers()->first();

        $userTypes = $user->getUserTypes();
        $userType = $userTypes->get(0);

        $costs = 0.0;
        if ('service' !== $trip->getType()) {
            $costs = ($trip->getEndMileage() - $trip->getStartMileage()) * $userType->getPricePerUnit();
        }

        return $costs;
    }
}
