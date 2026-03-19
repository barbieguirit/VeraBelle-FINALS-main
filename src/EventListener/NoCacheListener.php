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

        // Only apply to authenticated pages (exclude login page)
        $route = $request->attributes->get('_route');
        
        // Skip for login and public pages
        if (in_array($route, ['app_login', 'app_register'])) {
            return;
        }

        // If user is authenticated, add no-cache headers
        if ($this->security->getUser()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }
}