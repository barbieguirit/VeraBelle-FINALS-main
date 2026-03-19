<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: 'kernel.request', priority: 0)]
class UserStatusListener
{
    public function __construct(
        private Security $security,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Only check on main requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Skip check for login page to avoid redirect loop
        if ($request->attributes->get('_route') === 'app_login') {
            return;
        }

        $user = $this->security->getUser();

        // If user is logged in and is a User entity
        if ($user instanceof User) {
            // Check if user is disabled
            if ($user->getStatus() === 'disabled') {
                // Clear the security token (log them out)
                $this->tokenStorage->setToken(null);
                
                // Invalidate the session
                $session = $request->getSession();
                $session->invalidate();
                
                // Add flash message
                $session->getFlashBag()->add('error', 'Your account has been disabled. Please contact an administrator.');
                
                // Redirect to login
                $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
                $event->setResponse($response);
            }
        }
    }
}