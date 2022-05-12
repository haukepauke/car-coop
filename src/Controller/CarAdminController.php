<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\UserType;
use App\Form\CarFormType;
use App\Repository\CarRepository;
use App\Repository\ExpenseRepository;
use App\Repository\PaymentRepository;
use App\Repository\TripRepository;
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

            $userType = new UserType();
            $userType->setCar($car);
            $userType->setName('CAR_ADMIN');
            $userType->setPricePerUnit(0.30);
            $userType->addUser($this->getUser());
            $em->persist($userType);

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

    #[Route('/admin/car/edit/{car}', name: 'app_car_edit')]
    public function edit(Car $car, EntityManagerInterface $em, Request $request): Response
    {
        $form = $this->createForm(CarFormType::class, $car);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $car = $form->getData();
            $em->persist($car);

            $userType = new UserType();
            $userType->setCar($car);
            $userType->setName('CAR_ADMIN');
            $userType->setPricePerUnit(0.30);
            $userType->addUser($this->getUser());
            $em->persist($userType);

            $em->flush();

            $this->addFlash('success', 'Car updated!');

            return $this->redirectToRoute('app_car_list');
        }

        return $this->render(
            'car_admin/edit.html.twig',
            [
                'carForm' => $form->createView(),
                'car' => $car,
            ]
        );
    }

    #[Route('/admin/car', name: 'app_car_list')]
    public function list(CarRepository $carRepo): Response
    {
        $cars = $carRepo->findAllForUser($this->getUser());

        return $this->render(
            'car_admin/list.html.twig',
            [
                'cars' => $cars,
            ]
        );
    }

    #[Route('/admin/car/show/{car}', name: 'app_car_show')]
    public function show(
        $car,
        CarRepository $carRepo,
        TripRepository $tripRepo,
        ExpenseRepository $expenseRepo,
        PaymentRepository $paymentRepo
    ): Response {
        $carObj = $carRepo->find($car);
        $trips = $tripRepo->findbyCar($carObj);
        $expenses = $expenseRepo->findByCar($carObj);
        $payments = $paymentRepo->findByCar($carObj);

        // users are only allowed to see their cars
        if ($carObj->hasUser($this->getUser())) {
            return $this->render(
                'car_admin/show.html.twig',
                [
                    'user' => $this->getUser(),
                    'car' => $carObj,
                    'trips' => $trips,
                    'expenses' => $expenses,
                    'payments' => $payments,
                ]
            );
        }

        return $this->redirectToRoute('app_car_list');
    }
}
