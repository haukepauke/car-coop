<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\CarHandbook;
use App\Entity\User;
use App\Service\FileUploaderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;

class CarHandbookAttachmentController extends AbstractController
{
    private const PHOTO_FOLDER = 'handbooks';

    #[Route('/admin/car/{car}/handbook/attachments/{filename}', name: 'app_car_handbook_attachment_show', methods: ['GET'], requirements: ['filename' => '.+'])]
    public function showAdminAttachment(Car $car, string $filename, FileUploaderService $uploader): BinaryFileResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User || !$car->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->createAttachmentResponse($car->getHandbook(), $filename, $uploader);
    }

    #[Route('/api/car_handbooks/{handbook}/attachments/{filename}', name: 'api_car_handbook_attachment_show', methods: ['GET'], requirements: ['filename' => '.+'])]
    public function showApiAttachment(CarHandbook $handbook, string $filename, FileUploaderService $uploader): BinaryFileResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User || !$handbook->getCar()->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->createAttachmentResponse($handbook, $filename, $uploader);
    }

    private function createAttachmentResponse(?CarHandbook $handbook, string $filename, FileUploaderService $uploader): BinaryFileResponse
    {
        if ($handbook === null || basename($filename) !== $filename || !$handbook->hasPhoto($filename)) {
            throw new NotFoundHttpException();
        }

        $path = $uploader->findMessageAttachmentPath($filename, self::PHOTO_FOLDER . '/' . $handbook->getCar()->getId());
        if ($path === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path);
        $mimeType = MimeTypes::getDefault()->guessMimeType($path);
        if (is_string($mimeType)) {
            $response->headers->set('Content-Type', $mimeType);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

        return $response;
    }
}
