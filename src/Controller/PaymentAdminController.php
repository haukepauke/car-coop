<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentFormType;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentAdminController extends AbstractController
{
    #[Route('/admin/payment/list/{page<\d+>}', name: 'app_payment_list')]
    public function list(PaymentRepository $payRepo, int $page = 1)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $queryBuilder = $payRepo->createFindByCarQueryBuilder($car);
        $pagination = new Pagerfanta(
            new QueryAdapter($queryBuilder)
        );
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/payment/list.html.twig',
            [
                'car' => $car,
                'pager' => $pagination,
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

        $form = $this->createForm(
            PaymentFormType::class,
            $payment,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $payment = $form->getData();

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
        /** @var User $user */
        $user = $this->getUser();
        if ($payment->getFromUser() !== $user && $payment->getToUser() !== $user) {
            $this->addFlash('error', 'You can only edit payments, you are involved in.');

            return $this->redirectToRoute('app_payment_list');
        }
        $car = $payment->getCar();
        $form = $this->createForm(
            PaymentFormType::class,
            $payment,
            ['car' => $car]
        );

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
    public function delete(EntityManagerInterface $em, Payment $payment)
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($payment->getFromUser() !== $user && $payment->getToUser() !== $user) {
            $this->addFlash('error', 'You can only delete payments, you are involved in.');

            return $this->redirectToRoute('app_payment_list');
        }

        $em->remove($payment);
        $em->flush();

        // inform other user

        $this->addFlash('success', 'Payment deleted.');

        return $this->redirectToRoute('app_payment_list');
    }
}
