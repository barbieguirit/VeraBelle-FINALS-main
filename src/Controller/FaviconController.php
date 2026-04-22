<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FaviconController extends AbstractController
{
    #[Route('/favicon.ico', name: 'app_favicon')]
    public function favicon(): Response
    {
        // Return 204 No Content to avoid 404 errors for missing favicon
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
