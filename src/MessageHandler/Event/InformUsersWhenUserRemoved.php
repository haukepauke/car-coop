<?php

namespace App\MessageHandler\Event;

use App\Message\Event\UserRemovedEvent;
use App\Repository\CarRepository;
use App\Service\BoardMessageService;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenUserRemoved
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly CarRepository $carRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
        private readonly BoardMessageService $boardMessageService,
    ) {
    }

    public function __invoke(UserRemovedEvent $event): void
    {
        $this->logger->info('Processing UserRemovedEvent', ['carId' => $event->getCarId(), 'userName' => $event->getUserName()]);

        $car = $this->carRepository->find($event->getCarId());
        if (!$car) {
            return;
        }

        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.user.removed')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/user.removed.html.twig')
            ->context(['userName' => $event->getUserName(), 'car' => $car]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);

        $this->boardMessageService->createSystemMessage($car, 'board_system.user_removed', [
            '%user%' => htmlspecialchars($event->getUserName()),
            '%car%'  => htmlspecialchars($car->getName()),
        ]);
    }
}
