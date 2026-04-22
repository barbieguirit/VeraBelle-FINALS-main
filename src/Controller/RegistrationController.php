<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): Response
    {
        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // New web registrations are customers by default
            $user->setRoles(['ROLE_USER']);

            // Generate verification token and mark as unverified
            $verificationToken = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate verification URL
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send verification email
            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);

            $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

}