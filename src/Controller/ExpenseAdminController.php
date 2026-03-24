<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Form\ExpenseFormType;
use App\Repository\ExpenseRepository;
use App\Service\ActiveCarService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExpenseAdminController extends AbstractController
{
    #[Route('/admin/expense/list/{page<\d+>}', name: 'app_expense_list')]
    public function list(ExpenseRepository $expRepo, ActiveCarService $activeCarService, Request $request, int $page = 1)
    {
        $car            = $activeCarService->getActiveCar();
        $availableYears = $expRepo->getAvailableYears($car);
        $currentYear    = (int) date('Y');
        $defaultYear    = in_array($currentYear, $availableYears, true) ? $currentYear : null;
        $year           = $request->query->has('year') ? ($request->query->get('year') !== '' ? (int) $request->query->get('year') : null) : $defaultYear;
        $userId         = ($u = $request->query->get('user')) !== null && $u !== '' ? (int) $u : null;

        $queryBuilder = $expRepo->createFindByCarQueryBuilder($car, $year, $userId);
        $pagination = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/expense/list.html.twig',
            [
                'car'            => $car,
                'pager'          => $pagination,
                'selectedYear'   => $year,
                'selectedUserId' => $userId,
                'availableYears' => $availableYears,
                'carUsers'       => $car->getUsers(),
                'total'          => $expRepo->getTotal($car, $year, $userId),
            ]
        );
    }

    #[Route('/admin/expense/new', name: 'app_expense_new')]
    public function new(EntityManagerInterface $em, Request $request, ActiveCarService $activeCarService, TranslatorInterface $translator): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car = $activeCarService->getActiveCar();

        $expense = new Expense();
        $expense->setEditor($user);
        $expense->setUser($user);
        $expense->setCar($car);

        $form = $this->createForm(
            ExpenseFormType::class, 
            $expense,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $expense = $form->getData();
            $em->persist($expense);
            $em->flush();

            $this->addFlash('success', $translator->trans('expenses.created'));

            return $this->redirectToRoute('app_expense_list');
        }

        return $this->render(
            'admin/expense/new.html.twig',
            [
                'expenseForm' => $form->createView(),
                'car' => $car,
            ]
        );
    }

    #[Route('/admin/expense/edit/{expense}', name: 'app_expense_edit')]
    public function edit(EntityManagerInterface $em, Request $request, Expense $expense, TranslatorInterface $translator): Response
    {
        $car = $expense->getCar();
        $form = $this->createForm(
            ExpenseFormType::class, 
            $expense,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip = $form->getData();
            $trip->setEditor($this->getUser());
            $em->persist($trip);
            $em->flush();

            $this->addFlash('success', $translator->trans('expenses.updated'));

            return $this->redirectToRoute('app_expense_list');
        }

        return $this->render(
            'admin/expense/edit.html.twig',
            [
                'expenseForm' => $form->createView(),
                'car' => $car,
                'expense' => $expense,
            ]
        );
    }

    #[Route('/admin/expense/delete/{expense}', name: 'app_expense_delete')]
    public function delete(EntityManagerInterface $em, Expense $expense, TranslatorInterface $translator)
    {
        if ($this->getUser() !== $expense->getEditor()) {
            $this->addFlash('error', $translator->trans('expenses.delete_not_allowed'));

            return $this->redirectToRoute('app_expense_list');
        }

        $em->remove($expense);
        $em->flush();

        $this->addFlash('success', $translator->trans('expenses.deleted'));

        return $this->redirectToRoute('app_expense_list');
    }
}
