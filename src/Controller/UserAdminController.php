<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Form\InvitationFormType;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserAdminController extends AbstractController
{
    #[Route('/admin/user/list', name: 'app_user_list')]
    public function list(UserRepository $userRepo, InvitationRepository $invitationRepo)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $users = $userRepo->findByCar($car);
        $invites = $invitationRepo->findByCar($car);
        $usergroups = $car->getUserTypes();

        return $this->render(
            'admin/user/list.html.twig',
            [
                'car' => $car,
                'users' => $users,
                'invitations' => $invites,
                'usergroups' => $usergroups,
            ]
        );

        return $this->redirectToRoute('app_car_list');
    }

    #[Route('/admin/user/invite', name: 'app_user_invite')]
    public function invite(EntityManagerInterface $em, Request $request)
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $invitation = new Invitation();
        $invitation->setCreatedBy($this->getUser());
        $invitation->setStatus('new');

        $form = $this->createForm(
            InvitationFormType::class,
            $invitation,
            ['car' => $car]
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

            return $this->redirectToRoute('app_user_list', ['car' => $car->getId()]);
        }

        return $this->render(
            'admin/user/invite.html.twig',
            [
                'invitationForm' => $form->createView(),
                'car' => $car,
            ]
        );
    }
}
