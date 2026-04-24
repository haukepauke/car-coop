<?php

namespace App\Controller\Api;

use App\Entity\Car;
use App\Entity\Message;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles POST /api/messages as multipart/form-data so attachments can be
 * uploaded together with the message text.
 *
 * Expected form fields:
 *   car      – integer car ID
 *   content  – string (may be empty when photos are provided)
 *   photos[] – one or more JPG, PNG, GIF, or PDF files (optional)
 */
class MessageApiCreateController extends AbstractController
{
    private const MAX_PHOTO_SIZE = 4 * 1024 * 1024; // 4 MB
    private const PHOTO_FOLDER   = 'messages';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUploaderService $uploader,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // ── Resolve car ───────────────────────────────────────────────────────
        $carId = $request->request->get('car');
        if (!$carId) {
            throw new BadRequestHttpException('The "car" field (integer ID) is required.');
        }

        $car = $this->em->find(Car::class, (int) $carId);
        if (!$car instanceof Car) {
            throw new BadRequestHttpException('Car not found.');
        }

        if (!$car->hasUser($user)) {
            throw new AccessDeniedHttpException('You do not have access to this car.');
        }

        // ── Validate content ──────────────────────────────────────────────────
        $content = trim($request->request->get('content', ''));

        /** @var UploadedFile[] $uploadedFiles */
        $uploadedFiles = $request->files->get('photos') ?? [];
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }
        $uploadedFiles = array_filter($uploadedFiles);

        if ($content === '' && empty($uploadedFiles)) {
            throw new BadRequestHttpException('Message must contain text or at least one attachment.');
        }

        // ── Handle attachment uploads ─────────────────────────────────────────
        $uploadDir = $this->uploader->getMessageAttachmentDirectory(self::PHOTO_FOLDER);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $photoFilenames = [];
        foreach ($uploadedFiles as $file) {
            if (!$file->isValid()) {
                continue;
            }
            if ($file->getSize() > self::MAX_PHOTO_SIZE) {
                continue; // skip oversized files silently; app should pre-validate
            }
            if (!$this->uploader->isAllowedMessageAttachment($file)) {
                continue;
            }
            try {
                $filename = $this->uploader->uploadMessageAttachment($file, self::PHOTO_FOLDER);
            } catch (\RuntimeException) {
                return $this->json(['message' => 'Attachment upload failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            if (file_exists($uploadDir . '/' . $filename)) {
                $photoFilenames[] = $filename;
            }
        }

        if ($content === '' && $photoFilenames === []) {
            return $this->json(['message' => 'Message must contain text or at least one saved attachment.'], Response::HTTP_BAD_REQUEST);
        }

        // ── Persist ───────────────────────────────────────────────────────────
        $message = new Message();
        $message->setCar($car);
        $message->setAuthor($user);
        $message->setContent($content);
        if ($photoFilenames) {
            $message->setPhotos($photoFilenames);
        }

        $this->em->persist($message);
        $this->em->flush();

        return $this->json($message, 201, [], ['groups' => ['message:read']]);
    }
}
