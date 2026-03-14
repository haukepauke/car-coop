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
    public function __construct(private readonly string $homepageUrl)
    {
    }

    #[Route('/', name: 'app_homepage')]
    public function homepage(): Response
    {
        return $this->redirect($this->homepageUrl);
    }
}
