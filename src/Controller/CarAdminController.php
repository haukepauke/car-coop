<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\UserType;
use App\Form\CarFormType;
use App\Message\Event\PricePerUnitChangedEvent;
use App\Repository\BookingRepository;
use App\Repository\CarRepository;
use App\Service\ActiveCarService;
use App\Service\CarChartService;
use App\Service\CarPdfExportService;
use App\Service\CarReviewService;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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
            $userType->setAdmin(true);
            $userType->setFixed(true);
            $em->persist($userType);

            // Set retired usergroup
            $retiredUserType = new UserType();
            $retiredUserType->setCar($car);
            $retiredUserType->setName('Retired');
            $retiredUserType->setPricePerUnit(0.00);
            $retiredUserType->setActive(false);
            $retiredUserType->setFixed(true);

            $em->persist($retiredUserType);

            $em->flush();

            $this->addFlash('success', 'Car created, Default Usergroup created!');

            return $this->redirectToRoute('app_car_show');
        }

        $isFirstCar = $this->getUser()->getUserTypes()->isEmpty();

        return $this->render(
            $isFirstCar ? 'admin/car/new_first.html.twig' : 'admin/car/new.html.twig',
            ['carForm' => $form->createView()],
        );
    }

    #[Route('/admin/car/activate/{id}', name: 'app_car_activate', methods: ['POST'])]
    public function activate(Car $car, ActiveCarService $activeCarService): Response
    {
        if (!$car->hasUser($this->getUser())) {
            throw $this->createAccessDeniedException();
        }
        $activeCarService->setActiveCar($car);
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/admin/car/edit', name: 'app_car_edit')]
    public function edit(EntityManagerInterface $em, Request $request, FileUploaderService $fileUploader, ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();

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
                'car'     => $car,
                'isAdmin' => $car->isAdminUser($this->getUser()),
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
        BookingRepository $bookingRepo,
        CarChartService $charts,
        ActiveCarService $activeCarService
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $cars = $carRepo->findAllForUser($user);

        if (empty($cars)) {
            return $this->redirectToRoute('app_car_new');
        }

        $activeCar = $activeCarService->getActiveCar();

        $firstDayOfYear    = new \DateTime('first day of January');
        $lastDayOfYear     = new \DateTime('last day of December');
        $allTimeStart      = new \DateTime('2000-01-01');
        $allTimeEnd        = new \DateTime('2099-12-31');

        $carPanels = [];
        foreach ($cars as $carObj) {
            $carPanels[] = [
                'car'                           => $carObj,
                'bookings'                      => $bookingRepo->findByCar(new \DateTime(), $lastDayOfYear, $carObj, 3),
                'distanceTravelled'             => $carObj->getDistanceTravelled($firstDayOfYear, $lastDayOfYear),
                'moneySpent'                    => $carObj->getMoneySpent($firstDayOfYear, $lastDayOfYear),
                'moneySpentFuel'                => $carObj->getMoneySpent($firstDayOfYear, $lastDayOfYear, 'fuel'),
                'calculatedCostsPerUnit'        => $carObj->getCalculatedCosts($firstDayOfYear, $lastDayOfYear),
                'calculatedCostsPerUnitAllYears' => $carObj->getCalculatedCosts($allTimeStart, $allTimeEnd),
                'distanceChart'          => $charts->getDistanceDrivenByUserChart($carObj, $firstDayOfYear, $lastDayOfYear),
                'balanceChart'           => $charts->getUserBalanceChart($carObj, $firstDayOfYear, $lastDayOfYear),
            ];
        }

        return $this->render('admin/car/show.html.twig', [
            'user'        => $user,
            'car'         => $activeCar,
            'activeCarId' => $activeCar?->getId(),
            'carPanels'   => $carPanels,
        ]);
    }

    #[Route('/admin/car/export/pdf', name: 'app_car_export_pdf')]
    public function exportPdf(CarPdfExportService $pdfExport, ActiveCarService $activeCarService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car  = $activeCarService->getActiveCar();

        if (!$car->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }

        $pdf      = $pdfExport->generate($car, $user->getLocale());
        $filename = sprintf(
            'car-export-%s-%s.pdf',
            preg_replace('/[^a-z0-9]/i', '-', $car->getLicensePlate() ?? $car->getName()),
            (new \DateTime())->format('Y-m-d')
        );

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/admin/car/review', name: 'app_car_review')]
    public function review(ActiveCarService $activeCarService, CarReviewService $reviewService): Response
    {
        $car = $activeCarService->getActiveCar();

        return $this->render('admin/car/review.html.twig', array_merge(
            ['car' => $car],
            $reviewService->buildReviewData($car),
        ));
    }

    #[Route('/admin/car/review/accept-price/{userType}', name: 'app_car_review_accept_price', methods: ['POST'])]
    public function acceptPrice(UserType $userType, Request $request, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        if (!$this->isCsrfTokenValid('accept_price_' . $userType->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $suggested = (float) $request->request->get('suggested');
        if ($suggested > 0) {
            $oldPrice = $userType->getPricePerUnit();
            $userType->setPricePerUnit($suggested);
            $em->flush();
            $bus->dispatch(new PricePerUnitChangedEvent($userType->getId(), $oldPrice, $suggested));
            $this->addFlash('success', 'car.review.price.accepted');
        }

        return $this->redirectToRoute('app_car_review');
    }

    #[Route('/admin/car/delete', name: 'app_car_delete_confirm', methods: ['GET'])]
    public function deleteConfirm(ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();

        if (!$car->isAdminUser($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('admin/car/delete.html.twig', [
            'car' => $car,
        ]);
    }

    #[Route('/admin/car/delete', name: 'app_car_delete', methods: ['POST'])]
    public function delete(
        EntityManagerInterface $em,
        Request $request,
        ActiveCarService $activeCarService
    ): Response {
        $car = $activeCarService->getActiveCar();

        if (!$car->isAdminUser($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('car_delete_' . $car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($request->request->get('car_name') !== $car->getName()) {
            $this->addFlash('error', 'Car name did not match. Deletion cancelled.');
            return $this->redirectToRoute('app_car_delete_confirm');
        }

        $em->remove($car);
        $em->flush();

        $this->addFlash('success', 'Car deleted.');

        return $this->redirectToRoute('app_car_show');
    }
}
