<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\InvitationEntryType;
use App\Form\InvitationFormType;
use App\Form\UserFormType;
use App\Message\Event\InvitationCreatedEvent;
use App\Message\Event\UserRemovedEvent;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Service\ActiveCarService;
use App\Service\FileUploaderService;
use App\Service\InvitationMailerService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserAdminController extends AbstractController
{
    use ActiveCarScopeTrait;

    public function __construct(private readonly InvitationMailerService $invitationMailer)
    {
    }

    #[Route('/admin/user/list', name: 'app_user_list')]
    public function list(UserRepository $userRepo, InvitationRepository $invitationRepo, ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();

        $users = $userRepo->findByCar($car);
        $invites = $invitationRepo->findByCar($car);
        $usergroups = $car->getUserTypes()->toArray();
        usort($usergroups, fn($a, $b) => $b->isActive() <=> $a->isActive());

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

    #[Route('/admin/user/invite/onboarding', name: 'app_user_invite_onboarding')]
    public function inviteOnboarding(EntityManagerInterface $em, Request $request, ActiveCarService $activeCarService, MessageBusInterface $messageBus, TranslatorInterface $translator, InvitationRepository $invitationRepo): Response
    {
        return $this->handleInviteRequest($em, $request, $activeCarService, $messageBus, $translator, $invitationRepo, 'admin/user/invite_onboarding.html.twig', 'app_car_show');
    }

    #[Route('/admin/user/invite', name: 'app_user_invite')]
    public function invite(EntityManagerInterface $em, Request $request, ActiveCarService $activeCarService, MessageBusInterface $messageBus, TranslatorInterface $translator, InvitationRepository $invitationRepo): Response
    {
        return $this->handleInviteRequest($em, $request, $activeCarService, $messageBus, $translator, $invitationRepo, 'admin/user/invite.html.twig', 'app_user_list');
    }

    private function handleInviteRequest(EntityManagerInterface $em, Request $request, ActiveCarService $activeCarService, MessageBusInterface $messageBus, TranslatorInterface $translator, InvitationRepository $invitationRepo, string $template, string $successRoute): Response
    {
        $car = $activeCarService->getActiveCar();
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        $form = $this->buildInviteForm($car, $currentUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            ['sent' => $sent, 'skipped' => $skipped] = $this->processInviteEntries(
                $form->getData()['entries'], $car, $currentUser, $em, $messageBus, $invitationRepo
            );

            if ($sent) {
                $this->addFlash('success', $translator->trans('invitation.sent', ['%emails%' => implode(', ', $sent)]));
            }
            if ($skipped) {
                $this->addFlash('warning', $translator->trans('invitation.already_pending', ['%emails%' => implode(', ', $skipped)]));
            }

            return $this->redirectToRoute($successRoute);
        }

        return $this->render($template, [
            'invitationForm' => $form->createView(),
            'car'            => $car,
        ]);
    }

    private function buildInviteForm(\App\Entity\Car $car, User $currentUser): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder(['entries' => [[]]])
            ->add('entries', CollectionType::class, [
                'entry_type'    => InvitationEntryType::class,
                'entry_options' => ['car' => $car, 'default_locale' => $currentUser->getLocale() ?? 'en'],
                'allow_add'     => true,
                'prototype'     => true,
                'label'         => false,
            ])
            ->getForm();
    }

    private function processInviteEntries(array $entries, \App\Entity\Car $car, User $currentUser, EntityManagerInterface $em, MessageBusInterface $messageBus, InvitationRepository $invitationRepo): array
    {
        $sent = [];
        $skipped = [];

        foreach ($entries as $entry) {
            if ($invitationRepo->hasPendingForEmailAndCar($entry['email'], $car)) {
                $skipped[] = $entry['email'];
                continue;
            }

            $invitation = new Invitation();
            $invitation->setCreatedBy($currentUser);
            $invitation->setStatus('new');
            $invitation->setHash(bin2hex(random_bytes(80)));
            $invitation->setCreatedAt(new DateTimeImmutable());
            $invitation->setEmail($entry['email']);
            $invitation->setUserType($entry['userType']);

            $em->persist($invitation);
            $em->flush();

            $locale = $entry['locale'] ?? 'en';
            $this->invitationMailer->send($invitation, $locale);

            $messageBus->dispatch(new InvitationCreatedEvent($invitation->getId()));
            $sent[] = $invitation->getEmail();
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    #[Route('/admin/invite/resend/{invite}', name: 'app_invite_resend', methods: ['POST'])]
    public function resendInvite(
        Request $request,
        Invitation $invite,
        EntityManagerInterface $em,
        MessageBusInterface $messageBus,
        UserRepository $userRepo,
        TranslatorInterface $translator,
        ActiveCarService $activeCarService,
    ): Response {
        $this->denyUnlessActiveCarScope($activeCarService, $invite->getUserType()->getCar());

        if (!$this->isCsrfTokenValid('invite_resend_' . $invite->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() !== $invite->getCreatedBy()?->getId()) {
            $this->addFlash('error', $translator->trans('invitation.resend_not_allowed'));
            return $this->redirectToRoute('app_user_list');
        }

        $existingUser = $userRepo->findOneBy(['email' => $invite->getEmail()]);
        $locale = $existingUser?->getLocale() ?? $invite->getCreatedBy()?->getLocale() ?? 'en';

        $invite->setStatus('expired');

        $resentInvitation = new Invitation();
        $resentInvitation->setCreatedBy($invite->getCreatedBy());
        $resentInvitation->setStatus('new');
        $resentInvitation->setHash(bin2hex(random_bytes(80)));
        $resentInvitation->setCreatedAt(new DateTimeImmutable());
        $resentInvitation->setEmail($invite->getEmail());
        $resentInvitation->setUserType($invite->getUserType());

        $em->persist($resentInvitation);
        $em->flush();

        $this->invitationMailer->send($resentInvitation, $locale);
        $messageBus->dispatch(new InvitationCreatedEvent($resentInvitation->getId()));
        $this->addFlash('success', $translator->trans('invitation.resent', ['%email%' => $resentInvitation->getEmail()]));

        return $this->redirectToRoute('app_user_list');
    }

    #[Route('/admin/user/edit', name: 'app_user_edit')]
    public function edit(EntityManagerInterface $em, Request $request, FileUploaderService $fileUploader, TranslatorInterface $translator): Response
    {
        /** @var User */
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

            // Update session locale immediately so the UI switches language without requiring re-login
            if ($user->getLocale()) {
                $request->getSession()->set('_locale', $user->getLocale());
            }

            $this->addFlash('success', $translator->trans('user.updated'));

            return $this->redirectToRoute('app_user_list');
        }

        return $this->render(
            'admin/user/edit.html.twig',
            [
                'userForm' => $form->createView(),
            ]
        );
    }

    #[Route('/admin/user/tour/hide', name: 'app_user_tour_hide', methods: ['POST'])]
    public function hideTour(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('hide_tour_' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $user->setShowWelcomeTour(false);
        $em->persist($user);
        $em->flush();

        return $this->json(['status' => 'ok']);
    }

    #[Route('/admin/user/delete-account', name: 'app_user_delete_account_confirm', methods: ['GET'])]
    public function deleteAccountConfirm(): Response
    {
        return $this->render('admin/user/delete_account.html.twig');
    }

    #[Route('/admin/user/delete-account', name: 'app_user_delete_account', methods: ['POST'])]
    public function deleteAccount(EntityManagerInterface $em, Request $request, MessageBusInterface $messageBus): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_delete_' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Capture data before deactivation/deletion changes or removes it
        $events = [];
        foreach ($user->getCars() as $car) {
            $events[] = new UserRemovedEvent($car->getId(), $user->getName());
        }

        if ($user->hasEntries()) {
            $user->deactivate();
            $user->anonymize();
            $em->persist($user);
            $em->flush();
        } else {
            $em->remove($user);
            $em->flush();
        }

        foreach ($events as $event) {
            $messageBus->dispatch($event);
        }

        return $this->redirectToRoute('app_logout');
    }

    #[Route('/admin/invite/delete/{invite}', name: 'app_invite_delete', methods: ['POST'])]
    public function deleteInvite(Request $request, EntityManagerInterface $em, Invitation $invite, TranslatorInterface $translator, ActiveCarService $activeCarService): Response
    {
        $this->denyUnlessActiveCarScope($activeCarService, $invite->getUserType()->getCar());

        if (!$this->isCsrfTokenValid('invite_delete_' . $invite->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('error.csrf_invalid'));

            return $this->redirectToRoute('app_user_list');
        }

        if ($this->getUser() !== $invite->getCreatedBy()) {
            $this->addFlash('error', $translator->trans('invitation.delete_not_allowed'));

            return $this->redirectToRoute('app_user_list');
        }

        $em->remove($invite);
        $em->flush();

        $this->addFlash('success', $translator->trans('invitation.deleted'));

        return $this->redirectToRoute('app_user_list');
    }
    #[Route('/admin/user/delete/{user}', name: 'app_user_delete', methods: ['POST'])]
    public function deleteUser(
        Request $request,
        EntityManagerInterface $em,
        User $user,
        ActiveCarService $activeCarService,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
    ): Response {
        $activeCar = $this->denyUnlessUserBelongsToActiveCar($activeCarService, $user);

        if (!$this->isCsrfTokenValid('user_delete_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('error.csrf_invalid'));

            return $this->redirectToRoute('app_user_list');
        }

        // Do not allow to delete users of other cars and do not allow to delete yourself
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('error', $translator->trans('user.delete.not_allowed'));

            return $this->redirectToRoute('app_user_list');
        }

        // Capture data before deactivation/deletion changes or removes it
        $event = new UserRemovedEvent($activeCar->getId(), $user->getName());

        // Only delete users that did not add any data (trips, payments, etc.), anonymize and deactivate
        // everyone else
        if ($user->hasEntries()) {
            $user->deactivate();
            $user->anonymize();
            $em->persist($user);
            $em->flush();

            $messageBus->dispatch($event);
            $this->addFlash('success', $translator->trans('user.delete.has_entries'));
        } else {
            $em->remove($user);
            $em->flush();

            $messageBus->dispatch($event);
            $this->addFlash('success', $translator->trans('user.deleted'));
        }

        return $this->redirectToRoute('app_user_list');
    }
}
