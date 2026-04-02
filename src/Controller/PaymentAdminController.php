<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentFormType;
use App\Repository\PaymentRepository;
use App\Service\ActiveCarService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaymentAdminController extends AbstractController
{
    #[Route('/admin/payment/list/{page<\d+>}', name: 'app_payment_list')]
    public function list(PaymentRepository $payRepo, ActiveCarService $activeCarService, Request $request, int $page = 1)
    {
        $user           = $this->getUser();
        $car            = $activeCarService->getActiveCar();
        $availableYears = $payRepo->getAvailableYears($car);
        $currentYear    = (int) date('Y');
        $defaultYear    = in_array($currentYear, $availableYears, true) ? $currentYear : null;
        $year           = $request->query->has('year') ? ($request->query->get('year') !== '' ? (int) $request->query->get('year') : null) : $defaultYear;

        $queryBuilder = $payRepo->createFindByCarQueryBuilder($car, $year);
        $pagination = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/payment/list.html.twig',
            [
                'car'            => $car,
                'pager'          => $pagination,
                'user'           => $user,
                'selectedYear'   => $year,
                'availableYears' => $availableYears,
                'total'          => $payRepo->getTotal($car, $year),
            ]
        );
    }

    #[Route('/admin/payment/new', name: 'app_payment_new')]
    public function new(Request $request, PaymentService $paymentService, ActiveCarService $activeCarService, TranslatorInterface $translator): Response
    {
        $car = $activeCarService->getActiveCar();

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

            $paymentService->createPayment($payment);
            $this->addFlash('success', $translator->trans('payments.created'));

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
    public function edit(Request $request, PaymentService $paymentService, Payment $payment, TranslatorInterface $translator): Response
    {
        $car = $payment->getCar();
        $form = $this->createForm(
            PaymentFormType::class,
            $payment,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $paymentService->updatePayment($form->getData());

            // inform other user

            $this->addFlash('success', $translator->trans('payments.updated'));

            return $this->redirectToRoute('app_payment_list');
        }

        return $this->render(
            'admin/payment/edit.html.twig',
            [
                'paymentForm' => $form->createView(),
                'car' => $car,
                'payment' => $payment,
            ]
        );

        return $this->redirectToRoute('app_payment_list');
    }

    #[Route('/admin/payment/delete/{payment}', name: 'app_payment_delete')]
    public function delete(EntityManagerInterface $em, Payment $payment, TranslatorInterface $translator)
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($payment->getFromUser() !== $user && $payment->getToUser() !== $user) {
            $this->addFlash('error', $translator->trans('payments.delete_not_allowed'));

            return $this->redirectToRoute('app_payment_list');
        }

        $em->remove($payment);
        $em->flush();

        // inform other user

        $this->addFlash('success', $translator->trans('payments.deleted'));

        return $this->redirectToRoute('app_payment_list');
    }
}
