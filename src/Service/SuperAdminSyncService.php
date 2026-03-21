<?php

namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SuperAdminSyncService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $superAdminEmails,
    ) {}

    public function getCurrentHash(): string
    {
        return md5($this->superAdminEmails);
    }

    public function sync(): void
    {
        $emails  = $this->parseEmails($this->superAdminEmails);
        $users   = $this->userRepo->findAll();
        $changed = false;

        $foundEmails = [];
        foreach ($users as $user) {
            $hasRole    = in_array('ROLE_ADMIN', $user->getRoles(), true);
            $shouldHave = in_array($user->getEmail(), $emails, true);

            if ($shouldHave) {
                $foundEmails[] = $user->getEmail();
            }

            if ($shouldHave && !$hasRole) {
                $roles = array_values(array_unique(array_merge($user->getRoles(), ['ROLE_ADMIN'])));
                // Strip auto-added ROLE_USER before persisting (getRoles() re-adds it)
                $user->setRoles(array_values(array_diff($roles, ['ROLE_USER'])));
                $changed = true;
            } elseif (!$shouldHave && $hasRole) {
                $user->setRoles(array_values(array_diff($user->getRoles(), ['ROLE_ADMIN', 'ROLE_USER'])));
                $changed = true;
            }
        }

        foreach (array_diff($emails, $foundEmails) as $missing) {
            $this->logger->warning('SuperAdmin sync: no user found for email "{email}" from SUPERADMIN env var.', [
                'email' => $missing,
            ]);
        }

        if ($changed) {
            $this->em->flush();
        }
    }

    /** @return string[] */
    private function parseEmails(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $value))));
    }
}
