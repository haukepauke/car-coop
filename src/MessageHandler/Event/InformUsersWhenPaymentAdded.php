<?php

namespace App\MessageHandler\Event;

use App\Message\Event\PaymentAddedEvent;
use App\Repository\PaymentRepository;
use App\Service\EventMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class InformUsersWhenPaymentAdded
{
    public function __construct(
        private readonly EventMailerService $mailer,
        private readonly PaymentRepository $paymentRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PaymentAddedEvent $event): void
    {
        $this->logger->info('Processing PaymentAddedEvent', ['paymentId' => $event->getPaymentId()]);

        $payment = $this->paymentRepository->find($event->getPaymentId());
        if (!$payment) {
            return;
        }

        $car   = $payment->getCar();
        $users = $car->getActiveUsers();

        $email = (new TemplatedEmail())
            ->subject('event_email.payment.added')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/payment.added.html.twig')
            ->context(['payment' => $payment, 'car' => $car]);

        $this->mailer->sendMails($users, $email, ['%car%' => $car->getName()]);
    }
}
