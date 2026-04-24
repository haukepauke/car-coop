<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserType;
use App\Form\UserTypeFormType;
use App\Message\Event\PricePerUnitChangedEvent;
use App\Repository\UserRepository;
use App\Repository\UserTypeRepository;
use App\Service\ActiveCarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserGroupAdminController extends AbstractController
{
    use ActiveCarScopeTrait;

    #[Route('/admin/usergroup/new', name: 'app_usergroup_new')]
    public function new(EntityManagerInterface $em, Request $request, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $car = $user->getCar();

        $usergroup = new UserType();
        $usergroup->setCar($car);

        $form = $this->createForm(UserTypeFormType::class, $usergroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash('success', $translator->trans('user.group.created'));

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
    public function edit(EntityManagerInterface $em, Request $request, UserType $usergroup, MessageBusInterface $bus, TranslatorInterface $translator, ActiveCarService $activeCarService): Response
    {
        $this->denyUnlessActiveCarScope($activeCarService, $usergroup->getCar());

        if (!$usergroup->isActive()) {
            throw $this->createAccessDeniedException();
        }

        $carObj   = $usergroup->getCar();
        $oldPrice = $usergroup->getPricePerUnit();

        $form = $this->createForm(UserTypeFormType::class, $usergroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            if ($usergroup->getPricePerUnit() !== $oldPrice) {
                $bus->dispatch(new PricePerUnitChangedEvent($usergroup->getId(), $oldPrice, $usergroup->getPricePerUnit()));
            }

            $this->addFlash('success', $translator->trans('user.group.updated'));

            return $this->redirectToRoute('app_user_list', ['car' => $carObj->getId()]);
        }

        $otherGroups = $carObj->getUserTypes()->filter(
            fn(UserType $g) => $g->getId() !== $usergroup->getId() && $g->isActive()
        );

        return $this->render(
            'admin/usergroup/edit.html.twig',
            [
                'usergroupForm' => $form->createView(),
                'car'           => $carObj,
                'usergroup'     => $usergroup,
                'otherGroups'   => $otherGroups,
            ]
        );
    }

    #[Route('/admin/usergroup/{usergroup}/move-user', name: 'app_usergroup_move_user', methods: ['POST'])]
    public function moveUser(
        Request $request,
        UserType $usergroup,
        UserRepository $userRepository,
        UserTypeRepository $userTypeRepository,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        ActiveCarService $activeCarService,
    ): Response {
        $this->denyUnlessActiveCarScope($activeCarService, $usergroup->getCar());

        $car = $usergroup->getCar();

        if (!$this->isCsrfTokenValid('move_user_' . $usergroup->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('error.csrf_invalid'));

            return $this->redirectToRoute('app_usergroup_edit', ['usergroup' => $usergroup->getId()]);
        }

        $user        = $userRepository->find($request->request->get('user_id'));
        $targetGroup = $userTypeRepository->find($request->request->get('target_group_id'));

        if (!$user || !$targetGroup || $targetGroup->getCar() !== $car) {
            $this->addFlash('error', $translator->trans('error.invalid_request'));

            return $this->redirectToRoute('app_usergroup_edit', ['usergroup' => $usergroup->getId()]);
        }

        $user->removeUserType($usergroup);
        $user->addUserType($targetGroup);

        $em->flush();

        $this->addFlash('success', $translator->trans('user.group.user_moved'));

        return $this->redirectToRoute('app_usergroup_edit', ['usergroup' => $usergroup->getId()]);
    }

    #[Route('/admin/usergroup/delete/{usergroup}', name: 'app_usergroup_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, UserType $usergroup, TranslatorInterface $translator, ActiveCarService $activeCarService): Response
    {
        $this->denyUnlessActiveCarScope($activeCarService, $usergroup->getCar());

        $car = $usergroup->getCar();

        if (!$this->isCsrfTokenValid('usergroup_delete_' . $usergroup->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('error.csrf_invalid'));

            return $this->redirectToRoute('app_usergroup_edit', ['usergroup' => $usergroup->getId()]);
        }

        // only allow to delete groups without users
        if (0 === count($usergroup->getUsers()) && !$usergroup->isFixed()) {
            $em->remove($usergroup);
            $em->flush();

            $this->addFlash('success', $translator->trans('user.group.deleted'));
        } else {
            $this->addFlash('error', $translator->trans('user.group.delete_blocked'));
        }

        return $this->redirectToRoute('app_user_list', ['car' => $car->getId()]);
    }
}
