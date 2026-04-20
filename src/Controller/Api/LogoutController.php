<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\RefreshTokenManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
class LogoutController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenManager $refreshTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if (null === $payload) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $payload['refresh_token'] ?? null;
        if (!is_string($refreshToken) || '' === trim($refreshToken)) {
            return $this->json(['message' => 'Missing refresh_token.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User || !$this->refreshTokenManager->revoke($refreshToken, $user)) {
            return $this->json(['message' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function decodeJson(Request $request): ?array
    {
        if ('' === trim($request->getContent())) {
            return [];
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }
}
