<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\User;
use App\Message\Event\SuperAdminBroadcastEvent;
use App\Repository\CarRepository;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
class SuperAdminController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    #[Route('/superadmin', name: 'app_superadmin')]
    public function index(CarRepository $carRepo, UserRepository $userRepo): Response
    {
        return $this->render('superadmin/index.html.twig', [
            'cars'  => $carRepo->findBy([], ['name' => 'ASC']),
            'users' => $userRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/superadmin/user/{id}/delete', name: 'app_superadmin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $em, ResetPasswordRequestRepository $resetPasswordRepo): Response
    {
        if (!$this->isCsrfTokenValid('superadmin_user_delete_' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($user->getId() === $currentUser->getId()) {
            $this->addFlash('error', $this->translator->trans('superadmin.cannot_delete_self'));
            return $this->redirectToRoute('app_superadmin');
        }

        if ($user->hasEntries()) {
            $user->deactivate();
            $user->anonymize();
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', $this->translator->trans('superadmin.user_deactivated'));
        } else {
            $resetPasswordRepo->removeRequests($user);
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', $this->translator->trans('superadmin.user_deleted'));
        }

        return $this->redirectToRoute('app_superadmin');
    }

    #[Route('/superadmin/broadcast', name: 'app_superadmin_broadcast', methods: ['POST'])]
    public function broadcast(Request $request, MessageBusInterface $bus): Response
    {
        if (!$this->isCsrfTokenValid('superadmin_broadcast', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $subject = trim($request->request->get('subject', ''));
        $content = trim($request->request->get('content', ''));

        if ($subject === '' || $content === '') {
            $this->addFlash('error', $this->translator->trans('superadmin.broadcast.error_empty'));
            return $this->redirectToRoute('app_superadmin');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $bus->dispatch(new SuperAdminBroadcastEvent($subject, $content, $currentUser->getId()));

        $this->addFlash('success', $this->translator->trans('superadmin.broadcast.sent'));
        return $this->redirectToRoute('app_superadmin');
    }

    #[Route('/superadmin/car/{id}/delete', name: 'app_superadmin_car_delete', methods: ['POST'])]
    public function deleteCar(Car $car, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('superadmin_car_delete_' . $car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($car);
        $em->flush();
        $this->addFlash('success', $this->translator->trans('superadmin.car_deleted'));

        return $this->redirectToRoute('app_superadmin');
    }
}
