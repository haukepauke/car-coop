<?php

namespace App\Controller;

use App\Entity\UserType;
use App\Form\UserTypeFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserGroupAdminController extends AbstractController
{
    #[Route('/admin/usergroup/new', name: 'app_usergroup_new')]
    public function new(EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $usergroup = new UserType();
        $usergroup->setCar($car);

        $form = $this->createForm(UserTypeFormType::class, $usergroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $usergroup = $form->getData();

            $em->persist($usergroup);
            $em->flush();

            $this->addFlash('success', 'Usergroup created!');

            return $this->redirectToRoute('app_user_list', ['car' => $car->getId()]);
        }

        return $this->render(
            'admin/usergroup/new.html.twig',
            [
                'usergroupForm' => $form->createView(),
                'car' => $car,
            ]
        );
    }

    #[Route('/admin/usergroup/edit/{usergroup}', name: 'app_usergroup_edit')]
    public function edit(EntityManagerInterface $em, Request $request, UserType $usergroup): Response
    {
        $carObj = $usergroup->getCar();
        $form = $this->createForm(UserTypeFormType::class, $usergroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $usergroup = $form->getData();

            // TODO:
            // - if user is in another group for the same car, remove user from that group
            // - if user has no group after update, abort with error message

            $em->persist($usergroup);
            $em->persist($carObj);
            $em->flush();

            $this->addFlash('success', 'Usergroup updated!');

            return $this->redirectToRoute('app_user_list', ['car' => $carObj->getId()]);
        }

        return $this->render(
            'admin/usergroup/edit.html.twig',
            [
                'usergroupForm' => $form->createView(),
                'car' => $carObj,
            ]
        );

        return $this->redirectToRoute('app_user_list', ['car' => $carObj->getId()]);
    }

    #[Route('/admin/usergroup/delete/{usergroup}', name: 'app_usergroup_delete')]
    public function delete(EntityManagerInterface $em, UserType $usergroup)
    {
        $car = $usergroup->getCar();

        // only allow to delete groups without users
        if (0 === count($usergroup->getUsers())) {
            $em->remove($usergroup);
            $em->flush();

            $this->addFlash('success', 'Usergroup deleted.');
        } else {
            $this->addFlash('error', 'Usergroup deletion aborted. Usergroup still contains users.');
        }

        return $this->redirectToRoute('app_user_list', ['car' => $car->getId()]);
    }
}
