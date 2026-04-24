<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\User;
use App\Service\ActiveCarService;

trait ActiveCarScopeTrait
{
    private function getCurrentUserOrDeny(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function getActiveCarOrDeny(ActiveCarService $activeCarService): Car
    {
        $car = $activeCarService->getActiveCar();
        if (!$car instanceof Car) {
            throw $this->createAccessDeniedException();
        }

        return $car;
    }

    private function denyUnlessActiveCarScope(ActiveCarService $activeCarService, Car $resourceCar): Car
    {
        $activeCar = $this->getActiveCarOrDeny($activeCarService);
        $currentUser = $this->getCurrentUserOrDeny();

        if ($activeCar->getId() !== $resourceCar->getId() || !$resourceCar->hasUser($currentUser)) {
            throw $this->createAccessDeniedException();
        }

        return $activeCar;
    }

    private function denyUnlessUserBelongsToActiveCar(ActiveCarService $activeCarService, User $subject): Car
    {
        $activeCar = $this->getActiveCarOrDeny($activeCarService);

        if (!$activeCar->hasUser($subject)) {
            throw $this->createAccessDeniedException();
        }

        return $activeCar;
    }
}
