<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActiveCarService
{
    private const SESSION_KEY = 'activeCarId';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function getActiveCar(): ?Car
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $activeCarId = $this->requestStack->getSession()->get(self::SESSION_KEY);
        if ($activeCarId !== null) {
            foreach ($user->getCars() as $car) {
                if ($car->getId() === $activeCarId) {
                    return $car;
                }
            }
        }

        return $user->getCar();
    }

    public function setActiveCar(Car $car): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $car->getId());
    }
}
