<?php

namespace App\Controller;

use App\Entity\ParkingLocation;
use App\Repository\ParkingLocationRepository;
use App\Service\ActiveCarService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function save(Request $request, EntityManagerInterface $em, ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();

        if (!$this->isCsrfTokenValid('parking_save', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $parking = new ParkingLocation();
        $parking->setCar($car);
        $parking->setUser($user);
        $parking->setLatitude((float) $request->request->get('lat'));
        $parking->setLongitude((float) $request->request->get('lng'));

        $em->persist($parking);
        $em->flush();

        return $this->redirectToRoute('app_parking_show');
    }
}
