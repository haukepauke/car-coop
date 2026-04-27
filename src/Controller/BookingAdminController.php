<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Form\BookingFormType;
use App\Repository\BookingRepository;
use App\Service\ActiveCarService;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingAdminController extends AbstractController
{
    use ActiveCarScopeTrait;

    #[Route('/admin/booking', name: 'app_booking_show')]
    public function index(ActiveCarService $activeCarService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $activeCarService->getActiveCar();

        return $this->render(
            'admin/booking/calendar.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'car' => $car,
                'user' => $user,
            ]
        );
    }

    #[Route('/admin/booking/new', name: 'app_booking_new')]
    public function new(
        Request $request,
        BookingService $bookingService,
        ActiveCarService $activeCarService,
        BookingRepository $bookingRepository,
        TranslatorInterface $translator
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $activeCarService->getActiveCar();

        $booking = new Booking();
        $booking->setCar($car);
        $booking->setEditor($user);
        $booking->setUser($user);

        $form = $this->createForm(
            BookingFormType::class,
            $booking,
            [
                'car' => $car,
                'user' => $user,
                'validation_groups' => ['Default', 'booking:create'],
            ]
        );

        $form->handleRequest($request);
        $overlappingBookings = $this->findOverlappingBookings($booking, $bookingRepository);
        if ($form->isSubmitted() && $form->isValid()) {
            $bookingService->createBooking($form->getData());
            if ($overlappingBookings !== []) {
                $this->addFlash('warning', $translator->trans('booking.overlap.flash', [
                    '%count%' => count($overlappingBookings),
                ]));
            }
            $this->addFlash('success', $translator->trans('booking.created'));

            return $this->redirectToRoute('app_booking_show');
        }

        return $this->render(
            'admin/booking/new.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'bookingForm' => $form->createView(),
                'car' => $car,
                'user' => $user,
                'overlappingBookings' => $overlappingBookings,
            ]
        );
    }

    #[Route('/admin/booking/edit/{booking}', name: 'app_booking_edit')]
    public function edit(Request $request, BookingRepository $bookingRepo, BookingService $bookingService, TranslatorInterface $translator, ActiveCarService $activeCarService, $booking): Response
    {
        $booking = $bookingRepo->find($booking);
        $this->denyUnlessActiveCarScope($activeCarService, $booking->getCar());
        $car = $booking->getCar();
        $isPastBooking = $booking->getEndDate() < new \DateTimeImmutable();

        if ($isPastBooking && $request->isMethod('POST')) {
            return $this->redirectToRoute('app_booking_edit', ['booking' => $booking->getId()]);
        }

        $form = $this->createForm(
            BookingFormType::class,
            $booking,
            [
                'car' => $car,
                'read_only' => $isPastBooking,
                'validation_groups' => ['Default', 'booking:update'],
            ]
        );

        $form->handleRequest($request);
        $overlappingBookings = $this->findOverlappingBookings($booking, $bookingRepo);
        if ($form->isSubmitted() && $form->isValid()) {
            $booking = $form->getData();
            $booking->setEditor($this->getUser());
            $bookingService->updateBooking($booking);
            if ($overlappingBookings !== []) {
                $this->addFlash('warning', $translator->trans('booking.overlap.flash', [
                    '%count%' => count($overlappingBookings),
                ]));
            }
            $this->addFlash('success', $translator->trans('booking.updated'));

            return $this->redirectToRoute('app_booking_show');
        }

        return $this->render(
            'admin/booking/edit.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'bookingForm' => $form->createView(),
                'booking' => $booking,
                'car' => $car,
                'overlappingBookings' => $overlappingBookings,
                'isPastBooking' => $isPastBooking,
            ]
        );
    }

    #[Route('/admin/booking/delete/{booking}', name: 'app_booking_delete', methods: ['POST'])]
    public function delete(Request $request, BookingRepository $bookingRepo, BookingService $bookingService, TranslatorInterface $translator, ActiveCarService $activeCarService, $booking): Response
    {
        $booking = $bookingRepo->find($booking);
        $this->denyUnlessActiveCarScope($activeCarService, $booking->getCar());

        if (!$this->isCsrfTokenValid('booking_delete_' . $booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('error.csrf_invalid'));

            return $this->redirectToRoute('app_booking_edit', ['booking' => $booking->getId()]);
        }

        if ($this->getUser() !== $booking->getUser()) {
            $this->addFlash('error', $translator->trans('booking.delete_not_allowed'));

            return $this->redirectToRoute('app_booking_show');
        }

        $bookingService->deleteBooking($booking);
        $this->addFlash('success', $translator->trans('booking.deleted'));

        return $this->redirectToRoute('app_booking_show');
    }

    /**
     * @return Booking[]
     */
    private function findOverlappingBookings(Booking $booking, BookingRepository $bookingRepository): array
    {
        if ($booking->getCar() === null || $booking->getStartDate() === null || $booking->getEndDate() === null) {
            return [];
        }

        return $bookingRepository->findOverlappingBookings(
            $booking->getCar(),
            $booking->getStartDate(),
            $booking->getEndDate(),
            $booking
        );
    }
}
