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

class ExpenseAdminController extends AbstractController
{
    #[Route('/admin/expense/list/{page<\d+>}', name: 'app_expense_list')]
    public function list(ExpenseRepository $expRepo, ActiveCarService $activeCarService, Request $request, int $page = 1)
    {
        $car  = $activeCarService->getActiveCar();
        $year = $request->query->has('year') ? ($request->query->get('year') !== '' ? (int) $request->query->get('year') : null) : (int) date('Y');

        $queryBuilder = $expRepo->createFindByCarQueryBuilder($car, $year);
        $pagination = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/expense/list.html.twig',
            [
                'car'            => $car,
                'pager'          => $pagination,
                'selectedYear'   => $year,
                'availableYears' => $expRepo->getAvailableYears($car),
                'total'          => $expRepo->getTotal($car, $year),
            ]
        );
    }

    #[Route('/admin/expense/new', name: 'app_expense_new')]
    public function new(EntityManagerInterface $em, Request $request, ActiveCarService $activeCarService): Response
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

            $this->addFlash('success', 'Expense created!');

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
    public function edit(EntityManagerInterface $em, Request $request, Expense $expense): Response
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

            $this->addFlash('success', 'Expense updated!');

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
    public function delete(EntityManagerInterface $em, Expense $expense)
    {
        if ($this->getUser() !== $expense->getEditor()) {
            $this->addFlash('error', 'You can only delete expenses you created or edited.');

            return $this->redirectToRoute('app_expense_list');
        }

        $em->remove($expense);
        $em->flush();

        $this->addFlash('success', 'Expense deleted.');

        return $this->redirectToRoute('app_expense_list');
    }
}
