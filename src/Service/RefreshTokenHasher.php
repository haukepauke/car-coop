<?php

namespace App\Service;

final readonly class RefreshTokenHasher
{
    public function __construct(
        private string $kernelSecret,
    ) {
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->kernelSecret);
    }
}
