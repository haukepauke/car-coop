<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Service\ActiveCarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
    #[Route('/admin/messages', name: 'app_message_board')]
    public function index(MessageRepository $repo, ActiveCarService $activeCarService): Response
    {
        $car = $activeCarService->getActiveCar();
        $messages = $repo->findForCar($car);

        return $this->render('admin/message/index.html.twig', [
            'car'      => $car,
            'messages' => $messages,
        ]);
    }

    #[Route('/admin/messages/new', name: 'app_message_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em, ActiveCarService $activeCarService): Response
    {
        if (!$this->isCsrfTokenValid('message_new', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $content = trim($request->request->get('content', ''));
        if ($content === '' || $content === '<p><br></p>') {
            return $this->redirectToRoute('app_message_board');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car  = $activeCarService->getActiveCar();

        $message = new Message();
        $message->setCar($car);
        $message->setAuthor($user);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        return $this->redirectToRoute('app_message_board');
    }

    #[Route('/admin/messages/{id}/sticky', name: 'app_message_toggle_sticky', methods: ['POST'])]
    public function toggleSticky(Message $message, Request $request, EntityManagerInterface $em, ActiveCarService $activeCarService): Response
    {
        if (!$this->isCsrfTokenValid('message_sticky_' . $message->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $message->setIsSticky(!$message->isSticky());
        $em->flush();

        return $this->redirectToRoute('app_message_board');
    }

    #[Route('/admin/messages/{id}/delete', name: 'app_message_delete', methods: ['POST'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $em, ActiveCarService $activeCarService): Response
    {
        if (!$this->isCsrfTokenValid('message_delete_' . $message->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car  = $activeCarService->getActiveCar();

        if ($message->getAuthor() !== $user && !$car->isAdminUser($user)) {
            throw $this->createAccessDeniedException('You are not allowed to delete this message.');
        }

        $em->remove($message);
        $em->flush();

        return $this->redirectToRoute('app_message_board');
    }
}
