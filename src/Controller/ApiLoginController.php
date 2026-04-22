<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): JsonResponse
    {
        // When using lexik/jwt-authentication-bundle with json_login,
        // the security system intercepts this route and handles authentication.
        // If this controller is ever reached, return a clear JSON error.

        $lastUsername = $authenticationUtils->getLastUsername();
        $error = $authenticationUtils->getLastAuthenticationError();

        return new JsonResponse([
            'message' => 'Authentication is handled by the JSON login firewall.',
            'email' => $lastUsername,
            'error' => $error ? $error->getMessageKey() : null,
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
