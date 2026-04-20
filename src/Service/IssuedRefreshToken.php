<?php

namespace App\Service;

use App\Entity\RefreshToken;

final readonly class IssuedRefreshToken
{
    public function __construct(
        public string $plainTextToken,
        public RefreshToken $refreshToken,
    ) {
    }
}
