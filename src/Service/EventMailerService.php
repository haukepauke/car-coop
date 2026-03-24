<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventMailerService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private LocaleSwitcher $localeSwitcher;
    private TranslatorInterface $translator;
    private string $homepageUrl;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger, LocaleSwitcher $localeSwitcher, TranslatorInterface $translator, string $homepageUrl)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->localeSwitcher = $localeSwitcher;
        $this->translator = $translator;
        $this->homepageUrl = $homepageUrl;
    }

    public function sendMails(ArrayCollection $users, TemplatedEmail $email, array $subjectParams = [], bool $ignoreNotificationPreference = false): void
    {
        $this->logger->info('sendMails called', ['users' => $users->toArray()]);

        /** @var User $user */
        foreach ($users as $user) {
            if (!$user->isActive()) {
                $this->logger->info('Skipping user, inactive', ['user' => $user->getEmail()]);
                continue;
            }

            if (!$ignoreNotificationPreference && !$user->isNotifiedOnEvents()) {
                $this->logger->info('Skipping user, isNotifiedOnEvents is false', ['user' => $user->getEmail()]);
                continue;
            }

            $address = new Address($user->getEmail(), $user->getName());
            $this->logger->info('Sending mail', ['address' => $user->getEmail() . " " . $user->getName()]);

            $translatedSubject = $this->translator->trans($email->getSubject(), $subjectParams, null, $user->getLocale());

            $userEmail = (clone $email)
                ->to($address)
                ->subject($translatedSubject)
                ->context(array_merge($email->getContext(), [
                    'locale'         => $user->getLocale(),
                    'recipient_name' => $user->getName(),
                    'homepage_url'   => $this->homepageUrl,
                ]));

            $this->localeSwitcher->runWithLocale($user->getLocale(), function () use ($userEmail): void {
                $this->mailer->send($userEmail);
            });
        }
    }
}
