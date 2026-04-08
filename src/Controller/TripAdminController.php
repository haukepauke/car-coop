<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\TripFormType;
use App\Form\TripSplitFormType;
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
    public function edit(TripService $tripService, TripRepository $tripRepo, Request $request, Trip $trip, TranslatorInterface $translator): Response
    {
        $car  = $trip->getCar();
        $form = $this->createForm(
            TripFormType::class,
            $trip,
            ['car' => $car, 'edit_mode' => true]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $trip->setEditor($this->getUser());
            $tripService->updateTrip($trip);

            $this->addFlash('success', $translator->trans('trips.updated'));

            return $this->redirectToRoute('app_trip_list');
        }

        $lastTrip = $tripRepo->findLastByEndMileage($car);

        return $this->render(
            'admin/trip/edit.html.twig',
            [
                'tripForm'   => $form->createView(),
                'car'        => $car,
                'trip'       => $trip,
                'canDelete'  => $lastTrip !== null && $lastTrip->getId() === $trip->getId(),
            ]
        );
    }

    #[Route('/admin/trip/split/{trip}', name: 'app_trip_split')]
    public function split(TripService $tripService, TripRepository $tripRepo, Request $request, Trip $trip, TranslatorInterface $translator): Response
    {
        if ($trip->getMileage() <= 1) {
            $this->addFlash('error', $translator->trans('trips.split.not_possible'));
            return $this->redirectToRoute('app_trip_edit', ['trip' => $trip->getId()]);
        }

        $car      = $trip->getCar();
        $nextTrip = $tripRepo->findNextByMileage($trip);

        $trip2 = new Trip();
        $trip2->setCar($car);

        $form = $this->createForm(TripSplitFormType::class, [
            'startDate' => $trip->getStartDate(),
            'endDate'   => $trip->getEndDate(),
            'type'      => $trip->getType(),
            'users'     => $trip->getUsers()->toArray(),
            'comment'   => '',
        ], [
            'car'           => $car,
            'original_trip' => $trip,
            'next_trip'     => $nextTrip,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data         = $form->getData();
            $splitMileage = (int) $data['splitMileage'];

            $trip2->setStartMileage($splitMileage);
            $trip2->setEndMileage($trip->getEndMileage());
            $trip2->setStartDate($data['startDate']);
            $trip2->setEndDate($data['endDate']);
            $trip2->setType($data['type']);
            $trip2->setComment($data['comment'] ?? '');
            foreach ($trip2->getUsers()->toArray() as $u) {
                $trip2->removeUser($u);
            }
            foreach ($data['users'] as $u) {
                $trip2->addUser($u);
            }
            $trip2->setEditor($this->getUser());

            $tripService->splitTrip($trip, $splitMileage, $trip2);

            $this->addFlash('success', $translator->trans('trips.split.success'));

            return $this->redirectToRoute('app_trip_list');
        }

        return $this->render('admin/trip/split.html.twig', [
            'splitForm' => $form->createView(),
            'trip'      => $trip,
            'car'       => $car,
        ]);
    }

    #[Route('/admin/trip/delete/{trip}', name: 'app_trip_delete')]
    public function delete(EntityManagerInterface $em, TripRepository $tripRepo, Trip $trip, TranslatorInterface $translator)
    {
        $car      = $trip->getCar();
        $lastTrip = $tripRepo->findLastByEndMileage($car);

        if ($lastTrip !== null && $lastTrip->getId() === $trip->getId()) {
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
