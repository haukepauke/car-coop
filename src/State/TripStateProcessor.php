<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Trip;
use App\Entity\User;
use App\Service\TripService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TripStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Trip) {
            return $data;
        }

        if ($data->getCar() !== null) {
            foreach ($data->getUsers() as $tripUser) {
                if (!$data->getCar()->hasUser($tripUser)) {
                    throw new AccessDeniedHttpException('Trip users must belong to the selected car.');
                }
            }
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $data->setEditor($user);
        }

        if ($operation instanceof Post) {
            $this->tripService->createTrip($data);
        } else {
            $this->tripService->updateTrip($data);
        }

        return $data;
    }
}
