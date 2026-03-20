<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Placeholder route for the json_login firewall.
 * Symfony's router requires a real route at the check_path, but the security
 * firewall intercepts the request before this controller is ever invoked.
 */
#[Route('/api/login', name: 'api_login', methods: ['POST'])]
class LoginController
{
    public function __invoke(): JsonResponse
    {
        // The json_login authenticator handles this — this body is never reached.
        throw new \LogicException('This controller should not be called directly.');
    }
}
