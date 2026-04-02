<?php

namespace App\Controller;

use App\Repository\ParkingLocationRepository;
use App\Service\ActiveCarService;
use App\Service\ParkingLocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParkingLocationController extends AbstractController
{
    #[Route('/admin/parking', name: 'app_parking_show')]
    public function show(ParkingLocationRepository $repo, ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();
        $location = $repo->findLatestForCar($car);

        return $this->render('admin/parking/show.html.twig', [
            'car'      => $car,
            'location' => $location,
        ]);
    }

    #[Route('/admin/parking/save', name: 'app_parking_save', methods: ['POST'])]
    public function save(Request $request, ParkingLocationService $parkingLocationService, ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();

        if (!$this->isCsrfTokenValid('parking_save', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $parkingLocationService->save(
            $car,
            $user,
            (float) $request->request->get('lat'),
            (float) $request->request->get('lng'),
        );

        return $this->redirectToRoute('app_parking_show');
    }
}
