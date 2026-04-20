<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvitationMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserRepository $userRepository,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
    }

    public function send(Invitation $invitation, string $locale): void
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $invitation->getEmail()]);
        $template = null !== $existingUser
            ? 'admin/user/email/invite_existing.html.twig'
            : 'admin/user/email/invite.html.twig';

        $acceptUrl = $this->urlGenerator->generate(
            'app_invite_accept',
            ['hash' => $invitation->getHash()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->mailer->send(
            (new TemplatedEmail())
                ->locale($locale)
                ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                ->to($invitation->getEmail())
                ->subject($this->translator->trans('invitation.email.subject', [], 'messages', $locale))
                ->htmlTemplate($template)
                ->context([
                    'invitation' => $invitation,
                    'car' => $invitation->getUserType()->getCar(),
                    'inviter' => $invitation->getCreatedBy(),
                    'acceptUrl' => $acceptUrl,
                ])
        );
    }
}
