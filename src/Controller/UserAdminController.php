<?php

namespace App\Controller;

use App\Repository\CarRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserAdminController extends AbstractController
{
    #[Route('/admin/user', name: 'app_user')]
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    #[Route('/admin/user/list/{car}', name: 'app_user_list')]
    public function list(CarRepository $carRepo, UserRepository $userRepo, $car)
    {
        $carObj = $carRepo->find($car);
        $users = $userRepo->findByCar($carObj);

        // users are only allowed to see the users of their car
        if ($carObj->hasUser($this->getUser())) {
            return $this->render(
                'user_admin/list.html.twig',
                [
                    'car' => $carObj,
                    'users' => $users,
                ]
            );
        }

        return $this->redirectToRoute('app_car_list');
    }
}
