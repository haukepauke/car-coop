<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ParkingLocation;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Automatically sets the `editor` field (and `user` for ParkingLocation)
 * to the currently authenticated user before persisting.
 */
class EditorStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $inner,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            // Always override editor with the authenticated user
            if (method_exists($data, 'setEditor')) {
                $data->setEditor($user);
            }

            // For Booking and Expense: default user to current user if not provided
            if (method_exists($data, 'setUser') && method_exists($data, 'getUser')) {
                try {
                    $existing = $data->getUser();
                    if ($existing === null) {
                        $data->setUser($user);
                    }
                } catch (\Error) {
                    // Uninitialized typed property (e.g. ParkingLocation::$user)
                    $data->setUser($user);
                }
            }
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}
