<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Form\ExpenseFormType;
use App\Repository\CarRepository;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExpenseAdminController extends AbstractController
{
    #[Route('/admin/expense/new/{car}', name: 'app_expense_new')]
    public function new(EntityManagerInterface $em, CarRepository $carRepo, Request $request, $car): Response
    {
        $carObj = $carRepo->find($car);
        if (!$carObj->hasUser($this->getUser())) {
            $this->redirectToRoute('app_car_list');
        }

        $expense = new Expense();
        $expense->setUser($this->getUser());
        $expense->setCar($carObj);

        $form = $this->createForm(ExpenseFormType::class, $expense);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $expense = $form->getData();
            $em->persist($expense);
            $em->flush();

            $this->addFlash('success', 'Expense created!');

            return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
        }

        return $this->render(
            'expense_admin/new.html.twig',
            [
                'expenseForm' => $form->createView(),
                'car' => $carObj,
            ]
        );
    }

    #[Route('/admin/expense/edit/{expense}', name: 'app_expense_edit')]
    public function edit(EntityManagerInterface $em, Request $request, Expense $expense): Response
    {
        $carObj = $expense->getCar();
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

                return $this->redirectToRoute('app_car_show', ['car' => $carObj->getId()]);
            }

            return $this->render(
                'expense_admin/edit.html.twig',
                [
                    'expenseForm' => $form->createView(),
                    'car' => $carObj,
                ]
            );
        }

        return $this->redirectToRoute('app_trip_list', ['car' => $carObj->getId()]);
    }

    #[Route('/admin/expense/delete/{expense}', name: 'app_expense_delete')]
    public function delete(EntityManagerInterface $em, ExpenseRepository $expenseRepo, $expense)
    {
        $expense = $expenseRepo->find($expense);
        $car = $expense->getCar();
        $em->remove($expense);
        $em->flush();

        $this->addFlash('success', 'Expense deleted.');

        return $this->redirectToRoute('app_car_show', ['car' => $car->getId()]);
    }
}
