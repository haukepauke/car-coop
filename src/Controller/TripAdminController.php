<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\TripFormType;
use App\Repository\TripRepository;
use App\Service\ActiveCarService;
use App\Service\ParkingLocationService;
use App\Service\TripService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class TripAdminController extends AbstractController
{
    #[Route('/admin/trip/list/{page<\d+>}', name: 'app_trip_list')]
    public function list(TripRepository $tripsRepo, ActiveCarService $activeCarService, Request $request, int $page = 1)
    {
        $car            = $activeCarService->getActiveCar();
        $availableYears = $tripsRepo->getAvailableYears($car);
        $currentYear    = (int) date('Y');
        $defaultYear    = in_array($currentYear, $availableYears, true) ? $currentYear : null;
        $year           = $request->query->has('year') ? ($request->query->get('year') !== '' ? (int) $request->query->get('year') : null) : $defaultYear;
        $userId         = ($u = $request->query->get('user')) !== null && $u !== '' ? (int) $u : null;

        $queryBuilder = $tripsRepo->createFindByCarQueryBuilder($car, $year, $userId);

        $pagination = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/trip/list.html.twig',
            [
                'car'            => $car,
                'pager'          => $pagination,
                'selectedYear'   => $year,
                'selectedUserId' => $userId,
                'availableYears' => $availableYears,
                'carUsers'       => $car->getUsers(),
                'totals'         => $tripsRepo->getTotals($car, $year, $userId),
            ]
        );
    }

    #[Route('/admin/trip/new', name: 'app_trip_new')]
    public function new(
        TripService $tripService,
        ParkingLocationService $parkingLocationService,
        Request $request,
        ActiveCarService $activeCarService,
        TranslatorInterface $translator
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car = $activeCarService->getActiveCar();

        $trip = new Trip();
        $trip->setStartMileage($car->getMileage());
        $trip->setEditor($user);
        $trip->addUser($user);

        $trip->setCar($car);

        $form = $this->createForm(
            TripFormType::class,
            $trip,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $tripService->createTrip($trip);

            $lat = $request->request->get('parking_lat');
            $lng = $request->request->get('parking_lng');
            if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
                $parkingLocationService->save($car, $user, (float) $lat, (float) $lng);
            }

            $this->addFlash('success', $translator->trans('trips.created'));

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
    public function edit(TripService $tripService, Request $request, Trip $trip, TranslatorInterface $translator): Response
    {
        $car = $trip->getCar();
        $form = $this->createForm(
            TripFormType::class,
            $trip,
            ['car' => $car]
        );

        if ($car->getMileage() !== $trip->getEndMileage() && $trip->isCompleted() && !($form->isSubmitted() && $form->isValid())) {
            $this->addFlash('error', $translator->trans('trips.edit.blocked'));
        } else {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $trip = $form->getData();

                $trip->setEditor($this->getUser());
                $tripService->updateTrip($trip);

                $this->addFlash('success', $translator->trans('trips.updated'));

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
    public function delete(EntityManagerInterface $em, Trip $trip, TranslatorInterface $translator)
    {
        $car = $trip->getCar();

        // only allow to delete last trip for car
        if ($car->getMileage() === $trip->getEndMileage() || !$trip->isCompleted()) {
            $car->setMileage($trip->getStartMileage());
            $em->persist($car);
            $em->remove($trip);
            $em->flush();

            $this->addFlash('success', $translator->trans('trips.deleted'));
        } else {
            $this->addFlash('error', $translator->trans('trips.delete_blocked'));
        }

        return $this->redirectToRoute('app_trip_list');
    }
}
