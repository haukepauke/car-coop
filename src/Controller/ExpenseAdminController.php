<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Form\ExpenseFormType;
use App\Repository\ExpenseRepository;
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
    public function list(ExpenseRepository $expRepo, int $page = 1)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $queryBuilder = $expRepo->createFindByCarQueryBuilder($car);
        $pagination = new Pagerfanta(
            new QueryAdapter($queryBuilder)
        );
        $pagination->setMaxPerPage(20);
        $pagination->setCurrentPage($page);

        return $this->render(
            'admin/expense/list.html.twig',
            [
                'car' => $car,
                'pager' => $pagination,
            ]
        );
    }

    #[Route('/admin/expense/new', name: 'app_expense_new')]
    public function new(EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

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
