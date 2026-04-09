<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns the currently authenticated user's profile.
 */
class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'                 => $user->getId(),
            'email'              => $user->getEmail(),
            'name'               => $user->getName(),
            'color'              => $user->getColor(),
            'profilePicturePath' => $user->getProfilePicturePath(),
            'locale'             => $user->getLocale(),
            'roles'              => $user->getRoles(),
        ]);
    }
}
