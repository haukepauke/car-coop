<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\RefreshTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
class RefreshTokenController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenManager $refreshTokenManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
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

        $issuedRefreshToken = $this->refreshTokenManager->refresh($refreshToken);
        if (null === $issuedRefreshToken) {
            return $this->json(['message' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = $issuedRefreshToken->refreshToken->getUser();

        return $this->json([
            'token' => $this->jwtTokenManager->create($user),
            'refresh_token' => $issuedRefreshToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenTtl,
            'refresh_expires_in' => $this->refreshTokenTtl,
        ]);
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
