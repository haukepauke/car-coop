<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Form\ExpenseFormType;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExpenseAdminController extends AbstractController
{
    #[Route('/admin/expense/list', name: 'app_expense_list')]
    public function list(ExpenseRepository $expRepo)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $expenses = $expRepo->findByCar($car);

        return $this->render(
            'admin/expense/list.html.twig',
            [
                'car' => $car,
                'expenses' => $expenses,
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
        $expense->setUser($this->getUser());
        $expense->setCar($car);

        $form = $this->createForm(ExpenseFormType::class, $expense);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $expense = $form->getData();
            $em->persist($expense);
            $em->flush();

            $this->addFlash('success', 'Expense created!');

            return $this->redirectToRoute('app_car_show');
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
        $form = $this->createForm(ExpenseFormType::class, $expense);

        if ($this->getUser() !== $expense->getUser()) {
            $this->addFlash('error', 'You can only edit your own expenses.');
        } else {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $trip = $form->getData();

                $em->persist($trip);
                $em->flush();

                $this->addFlash('success', 'Expense updated!');

                return $this->redirectToRoute('app_car_show');
            }

            return $this->render(
                'admin/expense/edit.html.twig',
                [
                    'expenseForm' => $form->createView(),
                    'car' => $car,
                ]
            );
        }

        return $this->redirectToRoute('app_trip_list', ['car' => $car->getId()]);
    }

    #[Route('/admin/expense/delete/{expense}', name: 'app_expense_delete')]
    public function delete(EntityManagerInterface $em, ExpenseRepository $expenseRepo, $expense)
    {
        $expense = $expenseRepo->find($expense);

        if ($this->getUser() !== $expense->getUser()) {
            $this->addFlash('error', 'You can only delete your own expenses.');

            return $this->redirectToRoute('app_trip_list');
        }

        $em->remove($expense);
        $em->flush();

        $this->addFlash('success', 'Expense deleted.');

        return $this->redirectToRoute('app_car_show');
    }
}
