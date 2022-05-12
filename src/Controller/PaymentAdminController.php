<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentFormType;
use App\Repository\CarRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentAdminController extends AbstractController
{
    #[Route('/admin/payment/new/{car}', name: 'app_payment_new')]
    public function new(EntityManagerInterface $em, CarRepository $carRepo, Request $request, $car): Response
    {
        $carObj = $carRepo->find($car);
        if (!$carObj->hasUser($this->getUser())) {
            $this->redirectToRoute('app_car_list');
        }

        $payment = new Payment();
        $payment->setCar($carObj);

        $form = $this->createForm(PaymentFormType::class, $payment);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $payment = $form->getData();
            $payment->setFromUser($this->getUser());

            if ($payment->getToUser() === $payment->getFromUser()) {
                throw new Exception('Payment sender and receiver can not be the same user');
            }

            $em->persist($payment);
            $em->flush();

            // inform other user

            $this->addFlash('success', 'Payment created!');

            return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
        }

        return $this->render(
            'payment_admin/new.html.twig',
            [
                'paymentForm' => $form->createView(),
                'car' => $carObj,
            ]
        );
    }

    #[Route('/admin/payment/edit/{payment}', name: 'app_payment_edit')]
    public function edit(EntityManagerInterface $em, Request $request, Payment $payment): Response
    {
        $carObj = $payment->getCar();
        $form = $this->createForm(PaymentFormType::class, $payment);

        // TODO: check that user is either sender or receiver of payment before allowing to edit

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $em->persist($trip);
            $em->flush();

            // inform other user

            $this->addFlash('success', 'Payment updated!');

            return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
        }

        return $this->render(
            'payment_admin/edit.html.twig',
            [
                'paymentForm' => $form->createView(),
                'car' => $carObj,
            ]
        );

        return $this->redirectToRoute('app_trip_list', ['car' => $carObj->getId()]);
    }

    #[Route('/admin/payment/delete/{payment}', name: 'app_payment_delete')]
    public function delete(EntityManagerInterface $em, PaymentRepository $paymentRepo, $payment)
    {
        // ensure that user can only delete payment it is involved in

        $payment = $paymentRepo->find($payment);
        $car = $payment->getCar();
        $em->remove($payment);
        $em->flush();

        // inform other user

        $this->addFlash('success', 'Payment deleted.');

        return $this->redirectToRoute('app_car_show', ['car' => $car->getId()]);
    }
}
