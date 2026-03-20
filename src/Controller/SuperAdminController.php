<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\User;
use App\Repository\CarRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SuperAdminController extends AbstractController
{
    #[Route('/superadmin', name: 'app_superadmin')]
    public function index(CarRepository $carRepo, UserRepository $userRepo): Response
    {
        return $this->render('superadmin/index.html.twig', [
            'cars'  => $carRepo->findBy([], ['name' => 'ASC']),
            'users' => $userRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/superadmin/user/{id}/delete', name: 'app_superadmin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('superadmin_user_delete_' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_superadmin');
        }

        if ($user->hasEntries()) {
            $user->deactivate();
            $user->anonymize();
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'User has entries. Deletion not possible. User deactivated and anonymized instead.');
        } else {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'User deleted.');
        }

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
        $this->addFlash('success', 'Car deleted.');

        return $this->redirectToRoute('app_superadmin');
    }
}
