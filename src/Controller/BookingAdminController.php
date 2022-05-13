<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingAdminController extends AbstractController
{
    #[Route('/admin/booking', name: 'app_booking_show')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        return $this->render(
            'admin/booking/calendar.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'car' => $car,
            ]
        );
    }

    #[Route('/booking/admin/new', name: 'app_booking_new')]
    public function new(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        return $this->render(
            'admin/booking/new.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'car' => $car,
            ]
        );
    }
}
