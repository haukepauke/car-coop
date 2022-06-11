<?php

namespace App\Controller;

use App\Entity\UserType;
use App\Form\CarFormType;
use App\Repository\CarRepository;
use App\Repository\ExpenseRepository;
use App\Repository\PaymentRepository;
use App\Repository\TripRepository;
use App\Service\CarChartService;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

            // Set default usergroup
            $userType = new UserType();
            $userType->setCar($car);
            $userType->setName('Crew');
            $userType->setPricePerUnit(0.30);
            $userType->addUser($this->getUser());
            $em->persist($userType);

            $em->flush();

            $this->addFlash('success', 'Car created, Default Usergroup created!');

            return $this->redirectToRoute('app_car_show');
        }

        return $this->render(
            'admin/car/new.html.twig',
            [
                'carForm' => $form->createView(),
            ]
        );
    }

    #[Route('/admin/car/edit', name: 'app_car_edit')]
    public function edit(EntityManagerInterface $em, Request $request, FileUploaderService $fileUploader): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        // TODO: Disable mileage field when trips exist

        $form = $this->createForm(CarFormType::class, $car);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $car = $form->getData();
            $em->persist($car);

            /** @var UploadedFile $picture */
            $picture = $form->get('picture')->getData();
            if ($picture) {
                $pictureFilename = $fileUploader->upload($picture, 'cars');
                $car->setProfilePicturePath($pictureFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Car updated!');

            return $this->redirectToRoute('app_car_show');
        }

        return $this->render(
            'admin/car/edit.html.twig',
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
            'admin/car/list.html.twig',
            [
                'cars' => $cars,
            ]
        );
    }

    #[Route('/admin/car/show', name: 'app_car_show')]
    public function show(
        CarRepository $carRepo,
        TripRepository $tripRepo,
        ExpenseRepository $expenseRepo,
        PaymentRepository $paymentRepo,
        CarChartService $charts
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        if (null === $car) {
            return $this->redirectToRoute('app_car_new');
        }

        $carObj = $carRepo->find($car);
        $trips = $tripRepo->findbyCar($carObj);
        $expenses = $expenseRepo->findByCar($carObj);
        $payments = $paymentRepo->findByCar($carObj);

        // users are only allowed to see their cars
        if ($carObj->hasUser($this->getUser())) {
            return $this->render(
                'admin/car/show.html.twig',
                [
                    'user' => $this->getUser(),
                    'car' => $carObj,
                    'trips' => $trips,
                    'expenses' => $expenses,
                    'payments' => $payments,
                    'distanceChart' => $charts->getDistanceDrivenByUserChart($car),
                ]
            );
        }
    }
}
