<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class EventMailerService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendMails(ArrayCollection $users, TemplatedEmail $email)
    {
        // get all Users that have subscribed to events
        // send mails
    }
}
