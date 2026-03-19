<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiRegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['username'], $data['password'])) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Check if username already exists
        $existingUser = $entityManager->getRepository(User::class)
            ->findOneBy(['username' => $data['username']]);

        if ($existingUser) {
            return new JsonResponse(['error' => 'Username already taken'], 409);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'])
        );
        $user->setRoles(['ROLE_USER']);
        $user->setStatus('active');
        $user->setIsVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Registration successful',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ]
        ], 201);
    }
}