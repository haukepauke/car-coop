<?php

namespace App\Controller;

use App\Form\CarFormType;
use App\Repository\CarRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CarAdminController extends AbstractController
{
    #[Route('/admin/car/new', name: 'app_car_new')]
    public function new(EntityManagerInterface $em, Request $request): Response
    {
        $form = $this->createForm(CarFormType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $car = $form->getData();
            $em->persist($car);
            $em->flush();

            $this->addFlash('success', 'Car created!');

            return $this->redirectToRoute('app_car_list');
        }

        return $this->render(
            'car_admin/new.html.twig',
            [
                'carForm' => $form->createView(),
            ]
        );
    }

    #[Route('/admin/car', name: 'app_car_list')]
    public function list(CarRepository $carRepo)
    {
        $cars = $carRepo->findAll();

        return $this->render(
            'car_admin/list.html.twig',
            [
                'cars' => $cars,
            ]
        );
    }
}
