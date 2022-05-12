<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\TripFormType;
use App\Repository\CarRepository;
use App\Repository\TripRepository;
use App\Service\TripCostCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TripAdminController extends AbstractController
{
    #[Route('/admin/trip/new/{car}', name: 'app_trip_new')]
    public function new(TripCostCalculatorService $tc, EntityManagerInterface $em, CarRepository $carRepo, Request $request, $car): Response
    {
        $carObj = $carRepo->find($car);
        if (!$carObj->hasUser($this->getUser())) {
            $this->redirectToRoute('app_car_list');
        }

        $trip = new Trip();
        $trip->setStartMileage($carObj->getMileage());
        $trip->setUser($this->getUser());
        $trip->setCar($carObj);

        $form = $this->createForm(TripFormType::class, $trip);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $trip->setCosts($tc->calculateTripCosts($trip));

            if ($trip->isCompleted()) {
                $carObj->setMileage($trip->getEndMileage());
            }

            $em->persist($trip);
            $em->persist($carObj);
            $em->flush();

            $this->addFlash('success', 'Trip created!');

            return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
        }

        return $this->render(
            'trip_admin/new.html.twig',
            [
                'tripForm' => $form->createView(),
                'car' => $carObj,
            ]
        );
    }

    #[Route('/admin/trip/edit/{trip}', name: 'app_trip_edit')]
    public function edit(TripCostCalculatorService $tc, EntityManagerInterface $em, Request $request, Trip $trip): Response
    {
        $carObj = $trip->getCar();
        $form = $this->createForm(TripFormType::class, $trip);

        if ($carObj->getMileage() !== $trip->getEndMileage() && $trip->isCompleted() && !($form->isSubmitted() && $form->isValid())) {
            $this->addFlash('error', 'Trip editing aborted. Newer trips for this vehicle exist. Only the last trip can be edited.');
        } elseif ($this->getUser() !== $trip->getUser()) {
            $this->addFlash('error', 'You can only edit your own trips.');
        } else {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $trip = $form->getData();

                $trip->setCosts($tc->calculateTripCosts($trip));

                $carObj->setMileage($trip->getEndMileage());

                $em->persist($trip);
                $em->persist($carObj);
                $em->flush();

                $this->addFlash('success', 'Trip updated!');

                return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
            }

            return $this->render(
                'trip_admin/edit.html.twig',
                [
                    'tripForm' => $form->createView(),
                    'car' => $carObj,
                ]
            );
        }

        return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
    }

    #[Route('/admin/trip/delete/{trip}', name: 'app_trip_delete')]
    public function delete(EntityManagerInterface $em, TripRepository $tripRepo, $trip)
    {
        $trip = $tripRepo->find($trip);
        $car = $trip->getCar();

        // only allow to delete last trip for car
        if ($car->getMileage() === $trip->getEndMileage()) {
            $car->setMileage($trip->getStartMileage());
            $em->persist($car);
            $em->remove($trip);
            $em->flush();

            $this->addFlash('success', 'Trip deleted.');
        } else {
            $this->addFlash('error', 'Trip deletion aborted. Newer trips for this vehicle exist. Only the last trip can be deleted.');
        }

        return $this->redirectToRoute('app_car_show', ['car' => $car->getId()]);
    }
}
