<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentFormType;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentAdminController extends AbstractController
{
    #[Route('/admin/payment/list', name: 'app_payment_list')]
    public function list(PaymentRepository $payRepo)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $payments = $payRepo->findByCar($car);

        return $this->render(
            'admin/payment/list.html.twig',
            [
                'car' => $car,
                'payments' => $payments,
                'user' => $user,
            ]
        );
    }

    #[Route('/admin/payment/new', name: 'app_payment_new')]
    public function new(EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $payment = new Payment();
        $payment->setCar($car);

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

            // TODO: inform other user

            $this->addFlash('success', 'Payment created!');

            return $this->redirectToRoute('app_payment_list');
        }

        return $this->render(
            'admin/payment/new.html.twig',
            [
                'paymentForm' => $form->createView(),
                'car' => $car,
            ]
        );
    }

    #[Route('/admin/payment/edit/{payment}', name: 'app_payment_edit')]
    public function edit(EntityManagerInterface $em, Request $request, Payment $payment): Response
    {
        $car = $payment->getCar();
        $form = $this->createForm(PaymentFormType::class, $payment);

        // TODO: check that user is either sender or receiver of payment before allowing to edit

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();

            $em->persist($trip);
            $em->flush();

            // inform other user

            $this->addFlash('success', 'Payment updated!');

            return $this->redirectToRoute('app_payment_list');
        }

        return $this->render(
            'admin/payment/edit.html.twig',
            [
                'paymentForm' => $form->createView(),
                'car' => $car,
            ]
        );

        return $this->redirectToRoute('app_payment_list');
    }

    #[Route('/admin/payment/delete/{payment}', name: 'app_payment_delete')]
    public function delete(EntityManagerInterface $em, PaymentRepository $paymentRepo, $payment)
    {
        // ensure that user can only delete payment it is involved in

        $payment = $paymentRepo->find($payment);
        $em->remove($payment);
        $em->flush();

        // inform other user

        $this->addFlash('success', 'Payment deleted.');

        return $this->redirectToRoute('app_payment_list');
    }
}
