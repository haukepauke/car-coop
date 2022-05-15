<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\TripFormType;
use App\Repository\TripRepository;
use App\Service\TripCostCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TripAdminController extends AbstractController
{
    #[Route('/admin/trip/list', name: 'app_trip_list')]
    public function list(TripRepository $tripsRepo)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $trips = $tripsRepo->findByCar($car);

        return $this->render(
            'admin/trip/list.html.twig',
            [
                'car' => $car,
                'trips' => $trips,
            ]
        );
    }

    #[Route('/admin/trip/new', name: 'app_trip_new')]
    public function new(TripCostCalculatorService $tc, EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $trip = new Trip();
        $trip->setStartMileage($car->getMileage());
        $trip->setUser($this->getUser());
        $trip->setCar($car);

        $form = $this->createForm(TripFormType::class, $trip);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $trip->setCosts($tc->calculateTripCosts($trip));

            if ($trip->isCompleted()) {
                $car->setMileage($trip->getEndMileage());
            }

            $em->persist($trip);
            $em->persist($car);
            $em->flush();

            $this->addFlash('success', 'Trip created!');

            return $this->redirectToRoute('app_trip_list');
        }

        return $this->render(
            'admin/trip/new.html.twig',
            [
                'tripForm' => $form->createView(),
                'car' => $car,
            ]
        );
    }

    #[Route('/admin/trip/edit/{trip}', name: 'app_trip_edit')]
    public function edit(TripCostCalculatorService $tc, EntityManagerInterface $em, Request $request, Trip $trip): Response
    {
        $car = $trip->getCar();
        $form = $this->createForm(TripFormType::class, $trip);

        if ($car->getMileage() !== $trip->getEndMileage() && $trip->isCompleted() && !($form->isSubmitted() && $form->isValid())) {
            $this->addFlash('error', 'Trip editing aborted. Newer trips for this vehicle exist. Only the last trip can be edited.');
        } elseif ($this->getUser() !== $trip->getUser()) {
            $this->addFlash('error', 'You can only edit your own trips.');
        } else {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $trip = $form->getData();

                $trip->setCosts($tc->calculateTripCosts($trip));

                $car->setMileage($trip->getEndMileage());

                $em->persist($trip);
                $em->persist($car);
                $em->flush();

                $this->addFlash('success', 'Trip updated!');

                return $this->redirectToRoute('app_trip_list');
            }

            return $this->render(
                'admin/trip/edit.html.twig',
                [
                    'tripForm' => $form->createView(),
                    'car' => $car,
                ]
            );
        }

        return $this->redirectToRoute('app_trip_list');
    }

    #[Route('/admin/trip/delete/{trip}', name: 'app_trip_delete')]
    public function delete(EntityManagerInterface $em, Trip $trip)
    {
        $car = $trip->getCar();

        // only allow to delete last trip for car
        if ($car->getMileage() === $trip->getEndMileage() || !$trip->isCompleted()) {
            $car->setMileage($trip->getStartMileage());
            $em->persist($car);
            $em->remove($trip);
            $em->flush();

            $this->addFlash('success', 'Trip deleted.');
        } else {
            $this->addFlash('error', 'Trip deletion aborted. Newer trips for this vehicle exist. Only the last trip can be deleted.');
        }

        return $this->redirectToRoute('app_trip_list');
    }
}
