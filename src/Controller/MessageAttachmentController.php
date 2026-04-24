<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Service\FileUploaderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;

class MessageAttachmentController extends AbstractController
{
    private const PHOTO_FOLDER = 'messages';

    #[Route('/admin/messages/{message}/attachments/{filename}', name: 'app_message_attachment_show', methods: ['GET'], requirements: ['filename' => '.+'])]
    #[Route('/api/messages/{message}/attachments/{filename}', name: 'api_message_attachment_show', methods: ['GET'], requirements: ['filename' => '.+'])]
    public function __invoke(Message $message, string $filename, FileUploaderService $uploader): BinaryFileResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User || !$message->getCar()->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }

        if (basename($filename) !== $filename || !$message->hasPhoto($filename)) {
            throw new NotFoundHttpException();
        }

        $path = $uploader->findMessageAttachmentPath($filename, self::PHOTO_FOLDER);
        if ($path === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path);
        $mimeType = MimeTypes::getDefault()->guessMimeType($path);
        if (is_string($mimeType)) {
            $response->headers->set('Content-Type', $mimeType);
        }
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

        return $response;
    }
}
