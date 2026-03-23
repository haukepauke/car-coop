<?php

namespace App\MessageHandler\Event;

use App\Entity\Message;
use App\Message\Event\SuperAdminBroadcastEvent;
use App\Repository\CarRepository;
use App\Repository\UserRepository;
use App\Service\EventMailerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class HandleSuperAdminBroadcast
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CarRepository $carRepository,
        private readonly UserRepository $userRepository,
        private readonly EventMailerService $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {}

    public function __invoke(SuperAdminBroadcastEvent $event): void
    {
        $this->logger->info('Processing SuperAdminBroadcastEvent');

        $author = $this->userRepository->find($event->getAuthorId());

        // Post the message to every car's board
        foreach ($this->carRepository->findAll() as $car) {
            $message = new Message();
            $message->setCar($car);
            $message->setAuthor($author);
            $message->setContent('<p><strong>' . htmlspecialchars($event->getSubject(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>' . $event->getContent());
            $message->setIsBroadcast(true);
            $this->em->persist($message);
        }
        $this->em->flush();

        // Send one email per user
        $users = new ArrayCollection($this->userRepository->findAll());

        $email = (new TemplatedEmail())
            ->subject('event_email.broadcast.subject')
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->htmlTemplate('event/email/superadmin.broadcast.html.twig')
            ->context([
                'subject' => $event->getSubject(),
                'content' => $event->getContent(),
            ]);

        $this->mailer->sendMails($users, $email, ['%subject%' => $event->getSubject()]);
    }
}
