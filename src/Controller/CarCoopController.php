<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for static pages containing all the infos
 * regarding the application
 */
class CarCoopController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function homepage(): Response
    {
        return $this->render(
            'homepage.html.twig'
        );
    }

    #[Route('/how', name: 'app_how')]
    public function how(): Response {
        return $this->render(
            'how.html.twig'
        );
    }

    #[Route('/terms', name: 'app_terms')]
    public function terms(): Response {
        return $this->render(
            'terms.html.twig'
        );
    }

    #[Route('/privacy', name: 'app_privacy')]
    public function privacy(): Response {
        return $this->render(
            'privacy.html.twig'
        );
    }

    #[Route('/impressum', name: 'app_impressum')]
    public function impressum(): Response {
        return $this->render(
            'impressum.html.twig'
        );
    }
}
