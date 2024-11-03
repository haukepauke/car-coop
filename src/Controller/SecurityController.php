<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(
        path: '/{_locale}/login', 
        name: 'app_login',
        requirements: [
            '_locale' => 'en|de',
        ],
    )]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $this->addFlash('success', 'You are already logged in!');

            if (null === $this->getUser()->getCar()) {
                return $this->redirectToRoute('app_car_new');
            }

            return $this->redirectToRoute('app_car_show');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(
        path: '/logout', 
        name: 'app_logout'
    )]
    public function logout(Security $security): Response
    {
        $security->logout(false);
        return $this->redirect('https://car-coop.net');
    }
}
