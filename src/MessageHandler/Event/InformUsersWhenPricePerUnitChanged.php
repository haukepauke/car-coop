<?php

namespace App\MessageHandler\Event;

use App\Message\Event\PricePerUnitChangedEvent;
use App\Repository\UserTypeRepository;
use App\Service\BoardMessageService;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenPricePerUnitChanged
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly UserTypeRepository $userTypeRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
        private readonly BoardMessageService $boardMessageService,
    ) {}

    public function __invoke(PricePerUnitChangedEvent $event): void
    {
        $this->logger->info('Processing PricePerUnitChangedEvent', ['userTypeId' => $event->getUserTypeId()]);

        $userType = $this->userTypeRepository->find($event->getUserTypeId());
        if (!$userType) {
            return;
        }

        $car   = $userType->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.price_per_unit.changed')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/price_per_unit.changed.html.twig')
            ->context([
                'car'      => $car,
                'userType' => $userType,
                'oldPrice' => $event->getOldPrice(),
                'newPrice' => $event->getNewPrice(),
            ]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);

        $this->boardMessageService->createSystemMessage($car, 'board_system.price_per_unit_changed', [
            '%group%'     => htmlspecialchars($userType->getName()),
            '%car%'       => htmlspecialchars($car->getName()),
            '%old_price%' => number_format($event->getOldPrice(), 2),
            '%new_price%' => number_format($event->getNewPrice(), 2),
        ]);
    }
}
