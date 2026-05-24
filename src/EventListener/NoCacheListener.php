<?php

namespace App\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(event: 'kernel.response', priority: 0)]
class NoCacheListener
{
    public function __construct(
        private Security $security
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $route = $request->attributes->get('_route');

        // Login and registration forms should never be cached, otherwise the CSRF token can go stale.
        if (in_array($route, ['app_login', 'app_customer_login', 'app_customer_login_legacy', 'app_register'], true) || $this->security->getUser()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }
}