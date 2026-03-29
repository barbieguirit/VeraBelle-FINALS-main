<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\UserAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        VerifyEmailHelperInterface $verifyEmailHelper
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

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate signed email verification link
          // Generate signed email verification link
$signatureComponents = $verifyEmailHelper->generateSignature(
    'app_verify_email', // route name for verification
    $user->getId(),
    $user->getEmail(),
    ['id' => $user->getId()]
);

// Send verification email
$email = (new Email())
    ->from('noreply@verabelle.com')
    ->to($user->getEmail())
    ->subject('Please Confirm Your Email')
    ->html(
        $this->renderView('registration/confirmation_email.html.twig', [
            'signedUrl' => $signatureComponents->getSignedUrl(),
            'expirationMessageKey' => $signatureComponents->getExpirationMessageKey(),
            'expirationMessageData' => $signatureComponents->getExpirationMessageData(),
        ])
    );

    $mailer->send($email);

            $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $entityManager
    ): Response
    {
        $userId = $request->get('id');

        if (!$userId) {
            throw $this->createNotFoundException('No user ID provided.');
        }

        $user = $entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        // Validate the signed URL
        try {
            $verifyEmailHelper->validateEmailConfirmation($request->getUri(), $user->getId(), $user->getEmail());
            $user->setIsVerified(true);
            $entityManager->flush();

            $this->addFlash('success', 'Your email has been verified successfully!');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_register');
        }

        return $this->redirectToRoute('app_login');
    }
}