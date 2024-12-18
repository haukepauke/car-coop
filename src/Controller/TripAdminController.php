<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\TripFormType;
use App\Message\Event\TripAddedEvent;
use App\Repository\TripRepository;
use App\Service\TripCostCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class TripAdminController extends AbstractController
{
    #[Route('/admin/trip/list/{page<\d+>}', name: 'app_trip_list')]
    public function list(TripRepository $tripsRepo, int $page = 1)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $queryBuilder = $tripsRepo->createFindByCarQueryBuilder($car);

        $pagination = new Pagerfanta(
            new QueryAdapter($queryBuilder)
        );
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/trip/list.html.twig',
            [
                'car' => $car,
                'pager' => $pagination,
            ]
        );
    }

    #[Route('/admin/trip/new', name: 'app_trip_new')]
    public function new(
        TripCostCalculatorService $tc,
        EntityManagerInterface $em,
        Request $request,
        MessageBusInterface $messageBus
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $trip = new Trip();
        $trip->setStartMileage($car->getMileage());
        $trip->setEditor($user);
        $trip->setUser($user);

        $trip->setCar($car);

        $form = $this->createForm(
            TripFormType::class, 
            $trip,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $trip->setCosts($tc->calculateTripCosts($trip));

            if ($trip->isCompleted()) {
                $car->setMileage($trip->getEndMileage());
            }

            // Ensure Trip-Dates are after the last trip and start Milage is greater than
            // end Milage of last trip

            $em->persist($trip);
            $em->persist($car);
            $em->flush();

            $messageBus->dispatch(new TripAddedEvent($trip->getId()));
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
        $form = $this->createForm(
            TripFormType::class, 
            $trip,
            ['car' => $car]
        );

        if ($car->getMileage() !== $trip->getEndMileage() && $trip->isCompleted() && !($form->isSubmitted() && $form->isValid())) {
            $this->addFlash('error', 'Trip editing aborted. Newer trips for this vehicle exist. Only the last trip can be edited.');
        } else {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $trip = $form->getData();

                $trip->setCosts($tc->calculateTripCosts($trip));

                $car->setMileage($trip->getEndMileage());
                $trip->setEditor($this->getUser());
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
                    'trip' => $trip,
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
