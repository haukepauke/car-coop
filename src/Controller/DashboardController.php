<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard')]
    public function homepage(): Response
    {
        // list car(s) of user
        // list trips
        // list expenses
        // (show car calendar)

        return $this->render(
            'dashboard/index.html.twig',
            [
                '',
            ]
        );
    }
}
