<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Booking;
use App\Service\BookingService;

class BookingDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Booking) {
            $this->bookingService->deleteBooking($data);
        }

        return null;
    }
}
