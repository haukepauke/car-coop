<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Form\InvitationFormType;
use App\Repository\CarRepository;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserAdminController extends AbstractController
{
    #[Route('/admin/user/list/{car}', name: 'app_user_list')]
    public function list(CarRepository $carRepo, UserRepository $userRepo, $car, InvitationRepository $invitationRepo)
    {
        $carObj = $carRepo->find($car);
        $users = $userRepo->findByCar($carObj);
        $invites = $invitationRepo->findByCar($carObj);
        $usergroups = $carObj->getUserTypes();

        // users are only allowed to see the users of their car
        if ($carObj->hasUser($this->getUser())) {
            return $this->render(
                'admin/user/list.html.twig',
                [
                    'car' => $carObj,
                    'users' => $users,
                    'invitations' => $invites,
                    'usergroups' => $usergroups,
                ]
            );
        }

        return $this->redirectToRoute('app_car_list');
    }

    #[Route('/admin/user/invite/{car}', name: 'app_user_invite')]
    public function invite(EntityManagerInterface $em, Request $request, CarRepository $carRepo, $car)
    {
        $carObj = $carRepo->find($car);
        if (!$carObj->hasUser($this->getUser())) {
            $this->redirectToRoute('app_car_list');
        }

        $invitation = new Invitation();
        $invitation->setCreatedBy($this->getUser());
        $invitation->setStatus('new');

        $form = $this->createForm(
            InvitationFormType::class,
            $invitation,
            ['car' => $carObj]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $invitation = $form->getData();
            $invitation->setHash(bin2hex(random_bytes(80)));
            $invitation->setCreatedAt(new DateTimeImmutable());

            $em->persist($invitation);
            $em->flush();

            // send message for sending invitation email

            $this->addFlash('success', 'Invitation created!');

            return $this->redirectToRoute('app_user_list', ['car' => $carObj->getId()]);
        }

        return $this->render(
            'admin/user/invite.html.twig',
            [
                'invitationForm' => $form->createView(),
                'car' => $carObj,
            ]
        );
    }
}
