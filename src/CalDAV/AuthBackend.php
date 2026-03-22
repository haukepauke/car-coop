<?php

namespace App\CalDAV;

use App\Entity\User;
use App\Repository\UserRepository;
use Sabre\DAV\Auth\Backend\AbstractBasic;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthBackend extends AbstractBasic
{
    private ?User $currentUser = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        $this->realm = 'Car Coop CalDAV';
    }

    protected function validateUserPass($username, $password)
    {
        $user = $this->userRepository->findOneBy(['email' => $username]);

        if (!$user || !$user->isVerified() || !$user->isActive()) {
            return false;
        }

        if ($this->passwordHasher->isPasswordValid($user, $password)) {
            $this->currentUser = $user;
            return true;
        }

        return false;
    }

    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }
}
