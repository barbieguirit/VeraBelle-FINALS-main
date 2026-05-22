<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
#[AsEventListener(event: LogoutEvent::class)]
class AuthenticationListener
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}

    public function __invoke(LoginSuccessEvent|LogoutEvent $event): void
    {
        if ($event instanceof LoginSuccessEvent) {
            $user = $event->getUser();

            if ($user instanceof User) {
                $this->activityLogger->logLogin($user);
            }

            return;
        }

        if ($event instanceof LogoutEvent) {
            $token = $event->getToken();

            if ($token && $token->getUser() instanceof User) {
                $this->activityLogger->logLogout($token->getUser());
            }
        }
    }
}