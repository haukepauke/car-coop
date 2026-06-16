<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Booking;
use App\Entity\User;
use App\Service\BookingService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BookingStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Booking) {
            return $data;
        }

        if ($data->getCar() !== null && $data->getUser() !== null && !$data->getCar()->hasUser($data->getUser())) {
            throw new AccessDeniedHttpException('Booking user must belong to the selected car.');
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $data->setEditor($user);
        }

        if ($operation instanceof Post) {
            $this->bookingService->createBooking($data);
        } else {
            $this->bookingService->updateBooking($data);
        }

        return $data;
    }
}
