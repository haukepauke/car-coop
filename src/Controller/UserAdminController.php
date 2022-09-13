<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\InvitationFormType;
use App\Form\UserFormType;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Service\FileUploaderService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class UserAdminController extends AbstractController
{
    #[Route('/admin/user/list', name: 'app_user_list')]
    public function list(UserRepository $userRepo, InvitationRepository $invitationRepo): Response
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
    public function invite(EntityManagerInterface $em, Request $request, MailerInterface $mailer): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $invitation = new Invitation();
        $invitation->setCreatedBy($this->getUser());
        $invitation->setStatus('new');
        $invitation->setHash(bin2hex(random_bytes(80)));
        $invitation->setCreatedAt(new DateTimeImmutable());

        $form = $this->createForm(
            InvitationFormType::class,
            $invitation,
            ['car' => $car]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $invitation = $form->getData();

            // TODO Check if email address already exists in user table
            // TODO Check if email address already has invites

            $em->persist($invitation);
            $em->flush();

            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('webmaster@car-coop.net', 'Car Coop Mail Bot'))
                    ->to($invitation->getEmail())
                    ->subject('You have been invited to join car sharing with Car Coop!')
                    ->htmlTemplate(
                        'admin/user/email/invite.html.twig'
                    )
                    ->context(
                        [
                            'invitation' => $invitation,
                            'car' => $car,
                        ]
                    )
            );

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

    #[Route('/admin/user/edit', name: 'app_user_edit')]
    public function edit(EntityManagerInterface $em, Request $request, FileUploaderService $fileUploader): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserFormType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            /** @var UploadedFile $picture */
            $picture = $form->get('picture')->getData();
            if ($picture) {
                $pictureFilename = $fileUploader->upload($picture, 'users');
                $user->setProfilePicturePath($pictureFilename);
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User updated!');

            return $this->redirectToRoute('app_user_list');
        }

        return $this->render(
            'admin/user/edit.html.twig',
            [
                'userForm' => $form->createView(),
            ]
        );
    }

    #[Route('/admin/invite/delete/{invite}', name: 'app_invite_delete')]
    public function deleteInvite(EntityManagerInterface $em, Invitation $invite): Response
    {
        if ($this->getUser() !== $invite->getCreatedBy()) {
            $this->addFlash('error', 'Invitation was created by another user. You can only delete invites created by yourself.');
        }

        $em->remove($invite);
        $em->flush();

        $this->addFlash('success', 'Invitation deleted.');

        return $this->redirectToRoute('app_user_list');
    }

    #[Route('/admin/user/delete/{user}', name: 'app_user_delete')]
    public function deleteUser(
        EntityManagerInterface $em,
        User $user
    ): Response {
        // Do not allow to delete users of other cars and do not allow to delete yourself
        if ($this->getUser()->getCar() !== $user->getCar() || $this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'You are not allowed to delete this user.');
        }

        // Only delete users that did not add any data (trips, payments, etc.), anonymize and deactivate
        // everyone else
        if ($user->hasEntries()) {
            $user->deactivate();
            $user->anonymize();
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User has entries. Deletion not possible. User deactivated instead.');
        } else {
            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'User deleted.');
        }

        return $this->redirectToRoute('app_user_list');
    }
}
