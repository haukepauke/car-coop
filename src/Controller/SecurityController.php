<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(private readonly string $homepageUrl)
    {
    }

    #[Route(
        path: '/{_locale}/login',
        name: 'app_login',
        requirements: [
            '_locale' => 'en|de|nl|fr|es|pl',
        ],
    )]
    public function login(Request $request, AuthenticationUtils $authenticationUtils, TranslatorInterface $translator): Response
    {
        if ($this->getUser()) {
            if ($targetPath = $this->getTargetPath($request->getSession(), 'main')) {
                return new RedirectResponse($targetPath);
            }

            $this->addFlash('success', $translator->trans('user.already_logged_in'));

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
        // Use the response from logout() so that cookie-clearing headers
        // (e.g. remember-me) are sent to the browser, then redirect externally.
        $logoutResponse = $security->logout(false);

        $redirect = new RedirectResponse($this->homepageUrl);
        if ($logoutResponse !== null) {
            foreach ($logoutResponse->headers->getCookies() as $cookie) {
                $redirect->headers->setCookie($cookie);
            }
        }

        return $redirect;
    }
}
