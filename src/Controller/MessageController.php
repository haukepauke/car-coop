<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Service\ActiveCarService;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageController extends AbstractController
{
    use ActiveCarScopeTrait;

    private const MAX_PHOTO_SIZE = 4 * 1024 * 1024; // 4 MB
    private const PHOTO_FOLDER = 'messages';

    #[Route('/admin/messages/{page<\d+>}', name: 'app_message_board')]
    public function index(MessageRepository $repo, ActiveCarService $activeCarService, Request $request, int $page = 1): Response
    {
        $car            = $activeCarService->getActiveCar();
        $availableYears = $repo->getAvailableYears($car);
        $currentYear    = (int) date('Y');
        $defaultYear    = in_array($currentYear, $availableYears, true) ? $currentYear : null;
        $year           = $request->query->has('year') ? ($request->query->get('year') !== '' ? (int) $request->query->get('year') : null) : $defaultYear;

        $pagination = new Pagerfanta(new QueryAdapter($repo->createFindByCarQueryBuilder($car, $year)));
        $pagination->setMaxPerPage(10);
        $pagination->setCurrentPage($page);

        return $this->render('admin/message/index.html.twig', [
            'car'            => $car,
            'pager'          => $pagination,
            'selectedYear'   => $year,
            'availableYears' => $availableYears,
            'totalMessages'  => $repo->getCount($car, $year),
        ]);
    }

    #[Route('/admin/messages/new', name: 'app_message_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ActiveCarService $activeCarService,
        FileUploaderService $uploader,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid('message_new', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $content = trim($request->request->get('content', ''));
        /** @var UploadedFile[] $uploadedFiles */
        $uploadedFiles = $request->files->get('photos') ?? [];
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }
        $uploadedFiles = array_filter($uploadedFiles);

        if (($content === '' || $content === '<p><br></p>') && empty($uploadedFiles)) {
            return $this->redirectToRoute('app_message_board');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car  = $activeCarService->getActiveCar();

        $uploadDir = $uploader->getMessageAttachmentDirectory(self::PHOTO_FOLDER);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $photoFilenames = [];
        foreach ($uploadedFiles as $file) {
            if (!$file->isValid()) {
                $this->addFlash('warning', $translator->trans('messageboard.photo_upload_failed'));
                continue;
            }
            if ($file->getSize() > self::MAX_PHOTO_SIZE) {
                $this->addFlash('warning', $translator->trans('messageboard.photo_too_large'));
                continue;
            }
            if (!$uploader->isAllowedMessageAttachment($file)) {
                $this->addFlash('warning', $translator->trans('messageboard.photo_invalid_type'));
                continue;
            }
            try {
                $filename = $uploader->uploadMessageAttachment($file, self::PHOTO_FOLDER);
            } catch (\RuntimeException) {
                $this->addFlash('warning', $translator->trans('messageboard.photo_upload_failed'));
                continue;
            }
            if (file_exists($uploadDir . '/' . $filename)) {
                $photoFilenames[] = $filename;
            } else {
                $this->addFlash('warning', $translator->trans('messageboard.photo_upload_failed'));
            }
        }

        if (($content === '' || $content === '<p><br></p>') && $photoFilenames === []) {
            return $this->redirectToRoute('app_message_board');
        }

        $message = new Message();
        $message->setCar($car);
        $message->setAuthor($user);
        $message->setContent($content !== '' && $content !== '<p><br></p>' ? $content : '');
        if ($photoFilenames) {
            $message->setPhotos($photoFilenames);
        }

        $em->persist($message);
        $em->flush();

        return $this->redirectToRoute('app_message_board');
    }

    #[Route('/admin/messages/{id}/sticky', name: 'app_message_toggle_sticky', methods: ['POST'])]
    public function toggleSticky(Message $message, Request $request, EntityManagerInterface $em, ActiveCarService $activeCarService): Response
    {
        $this->denyUnlessActiveCarScope($activeCarService, $message->getCar());

        if (!$this->isCsrfTokenValid('message_sticky_' . $message->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $message->setIsSticky(!$message->isSticky());
        $em->flush();

        return $this->redirectToRoute('app_message_board');
    }

    #[Route('/admin/messages/{id}/delete', name: 'app_message_delete', methods: ['POST'])]
    public function delete(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        ActiveCarService $activeCarService,
        FileUploaderService $uploader,
    ): Response {
        $this->denyUnlessActiveCarScope($activeCarService, $message->getCar());

        if (!$this->isCsrfTokenValid('message_delete_' . $message->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $car  = $activeCarService->getActiveCar();

        if ($message->getAuthor() !== $user && !$car->isAdminUser($user)) {
            throw $this->createAccessDeniedException('You are not allowed to delete this message.');
        }

        foreach ($message->getPhotos() as $filename) {
            $uploader->deleteMessageAttachment($filename, self::PHOTO_FOLDER);
        }

        $em->remove($message);
        $em->flush();

        return $this->redirectToRoute('app_message_board');
    }
}
