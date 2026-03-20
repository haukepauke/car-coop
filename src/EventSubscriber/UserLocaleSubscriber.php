<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Stores the locale of the user in the session after login.
 * This can be used by the LocaleSubscriber afterwards.
 */
class UserLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        /** @var User $user */
        $user = $event->getAuthenticationToken()->getUser();

        $now = new \DateTime();

        if (null === $user->getFirstLogin()) {
            $user->setFirstLogin($now);
        }

        $user->setLastLogin($now);
        $this->em->flush();
    }

    /**
     * Fired for all successful authentications, including remember-me.
     * Sets the session locale so LocaleSubscriber can apply it on every request.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();

        // Skip stateless firewalls (e.g. the JWT API firewall).
        // Symfony sets _stateless=true on the request for stateless firewall contexts.
        if ($request->attributes->getBoolean('_stateless')) {
            return;
        }

        $user = $event->getUser();

        if ($user instanceof User && null !== $user->getLocale()) {
            $request->getSession()->set('_locale', $user->getLocale());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }
}
