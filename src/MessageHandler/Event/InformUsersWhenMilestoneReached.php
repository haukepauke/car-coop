<?php

namespace App\MessageHandler\Event;

use App\Message\Event\MilestoneReachedEvent;
use App\Repository\TripRepository;
use App\Service\BoardMessageService;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenMilestoneReached
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly BoardMessageService $boardMessageService,
        private readonly EventMailerService $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {}

    public function __invoke(MilestoneReachedEvent $event): void
    {
        $trip = $this->tripRepository->find($event->getTripId());
        if ($trip === null) {
            return;
        }

        $car                = $trip->getCar();
        $users              = $car->getActiveUsers();
        $unit               = $car->getMilageUnit() ?? 'km';
        $milestoneFormatted = number_format($event->getMilestone(), 0, '.', ',');

        $boardParams = [
            '%car%'  => htmlspecialchars($car->getName()),
            '%unit%' => htmlspecialchars($unit),
        ];
        if ($event->getType() === 'repeating') {
            $boardParams['%milestone%'] = $milestoneFormatted;
        }

        $this->logger->info('Handling MilestoneReachedEvent', [
            'milestone' => $event->getMilestone(),
            'car'       => $car->getName(),
        ]);

        $this->boardMessageService->createSystemMessage($car, $event->getBoardKey(), $boardParams);

        $milestoneEmail = (new TemplatedEmail())
            ->subject('event_email.milestone.subject')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/milestone.html.twig')
            ->context([
                'car'                  => $car,
                'milestoneMessageKey'  => $event->getBoardKey(),
                'milestoneBoardParams' => $boardParams,
            ]);

        $this->mailer->sendMails($users, $milestoneEmail, [
            '%car%'       => $car->getName(),
            '%milestone%' => $milestoneFormatted,
            '%unit%'      => $unit,
        ]);
    }
}
