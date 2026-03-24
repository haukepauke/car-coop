<?php

namespace App\MessageHandler\Event;

use App\Message\Event\MilestoneReachedEvent;
use App\Message\Event\TripAddedEvent;
use App\Repository\TripRepository;
use App\Service\MilestoneService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CheckMilestonesWhenTripAdded
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly MilestoneService $milestoneService,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TripAddedEvent $event): void
    {
        $trip = $this->tripRepository->find($event->getTripId());
        if ($trip === null) {
            return;
        }

        $startMileage = $trip->getStartMileage();
        $endMileage   = $trip->getEndMileage();
        if ($startMileage === null || $endMileage === null) {
            return;
        }

        foreach ($this->milestoneService->getCrossedMilestones($startMileage, $endMileage) as $milestone) {
            $this->logger->info('Milestone crossed, dispatching MilestoneReachedEvent', [
                'tripId'    => $event->getTripId(),
                'milestone' => $milestone['value'],
                'type'      => $milestone['type'],
            ]);
            $this->bus->dispatch(new MilestoneReachedEvent(
                $event->getTripId(),
                $milestone['value'],
                $milestone['type'],
                $milestone['boardKey'],
            ));
        }
    }
}
