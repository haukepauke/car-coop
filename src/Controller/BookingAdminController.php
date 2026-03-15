<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Form\BookingFormType;
use App\Message\Event\BookingAddedEvent;
use App\Message\Event\BookingDeletedEvent;
use App\Message\Event\BookingUpdatedEvent;
use App\Repository\BookingRepository;
use App\Service\ActiveCarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class BookingAdminController extends AbstractController
{
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
    public function new(Request $request, EntityManagerInterface $em, ActiveCarService $activeCarService, MessageBusInterface $messageBus): Response
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
                'user' => $user
            ]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $booking = $form->getData();
            $em->persist($booking);
            $em->flush();

            $messageBus->dispatch(new BookingAddedEvent($booking->getId()));
            $this->addFlash('success', 'Booking created!');

            return $this->redirectToRoute('app_booking_show');
        }

        return $this->render(
            'admin/booking/new.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'bookingForm' => $form->createView(),
                'car' => $car,
                'user' => $user,
            ]
        );
    }

    #[Route('/admin/booking/edit/{booking}', name: 'app_booking_edit')]
    public function edit(Request $request, BookingRepository $bookingRepo, EntityManagerInterface $em, MessageBusInterface $messageBus, $booking): Response
    {
        $booking = $bookingRepo->find($booking);
        $car = $booking->getCar();

        $form = $this->createForm(
            BookingFormType::class, 
            $booking,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $booking = $form->getData();
            $booking->setEditor($this->getUser());
            $em->persist($booking);
            $em->flush();

            $messageBus->dispatch(new BookingUpdatedEvent($booking->getId()));
            $this->addFlash('success', 'Booking updated!');

            return $this->redirectToRoute('app_booking_show');
        }

        return $this->render(
            'admin/booking/edit.html.twig',
            [
                'controller_name' => 'BookingAdminController',
                'bookingForm' => $form->createView(),
                'booking' => $booking,
                'car' => $car,
            ]
        );
    }

    #[Route('/admin/booking/delete/{booking}', name: 'app_booking_delete')]
    public function delete(EntityManagerInterface $em, BookingRepository $bookingRepo, MessageBusInterface $messageBus, $booking)
    {
        $booking = $bookingRepo->find($booking);

        if ($this->getUser() !== $booking->getUser()) {
            $this->addFlash('error', 'You can only delete your own bookings.');

            return $this->redirectToRoute('app_booking_show');
        }

        $event = new BookingDeletedEvent(
            $booking->getCar()->getId(),
            (string) $booking->getTitle(),
            $booking->getUser()->getName(),
            $booking->getStartDate()->format('Y-m-d H:i'),
            $booking->getEndDate()->format('Y-m-d H:i'),
        );

        $em->remove($booking);
        $em->flush();

        $messageBus->dispatch($event);
        $this->addFlash('success', 'Booking deleted.');

        return $this->redirectToRoute('app_booking_show');
    }
}
