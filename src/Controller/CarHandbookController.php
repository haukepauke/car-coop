<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\CarHandbook;
use App\Entity\User;
use App\Form\CarHandbookFormType;
use App\Service\ActiveCarService;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CarHandbookController extends AbstractController
{
    private const MAX_PHOTO_SIZE = 4 * 1024 * 1024; // 4 MB
    private const PHOTO_FOLDER = 'handbooks';
    private const PHOTO_PLACEHOLDER_PREFIX = 'handbook-upload://';

    #[Route('/admin/car/{car}/handbook', name: 'app_car_handbook_show', methods: ['GET'])]
    public function show(Car $car, ActiveCarService $activeCarService, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$car->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }

        $activeCarService->setActiveCar($car);

        $handbook = $car->getHandbook();
        $canManageHandbook = $car->canManageHandbook($user);
        $showEditor = false;
        $formView = null;

        if ($handbook === null && $canManageHandbook) {
            $handbook = (new CarHandbook())
                ->setCar($car)
                ->setContent($translator->trans('handbook.example_markdown'))
            ;
            $showEditor = true;
            $formView = $this->createForm(CarHandbookFormType::class, $handbook, [
                'action' => $this->generateUrl('app_car_handbook_edit', ['car' => $car->getId()]),
            ])->createView();
        }

        return $this->render('admin/car/handbook.html.twig', [
            'car' => $car,
            'handbook' => $handbook,
            'canManageHandbook' => $canManageHandbook,
            'showEditor' => $showEditor,
            'handbookForm' => $formView,
        ]);
    }

    #[Route('/admin/car/{car}/handbook/edit', name: 'app_car_handbook_edit', methods: ['GET', 'POST'])]
    public function edit(
        Car $car,
        Request $request,
        EntityManagerInterface $em,
        ActiveCarService $activeCarService,
        FileUploaderService $uploader,
        TranslatorInterface $translator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$car->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }
        if (!$car->canManageHandbook($user)) {
            throw $this->createAccessDeniedException();
        }

        $activeCarService->setActiveCar($car);

        $handbook = $car->getHandbook() ?? (new CarHandbook())
            ->setCar($car)
            ->setContent($translator->trans('handbook.example_markdown'));

        $isNew = $handbook->getId() === null;
        $form = $this->createForm(CarHandbookFormType::class, $handbook);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile[]|UploadedFile|null $uploadedFilesInput */
            $uploadedFilesInput = $form->get('photos')->getData();
            $uploadedFiles = is_array($uploadedFilesInput)
                ? array_filter($uploadedFilesInput)
                : array_filter([$uploadedFilesInput]);
            $photoTokensInput = $request->request->all('photoTokens');
            $photoTokens = is_array($photoTokensInput)
                ? array_values(array_filter($photoTokensInput, static fn (mixed $token): bool => is_string($token) && $token !== ''))
                : [];

            $photoFilenames = $handbook->getPhotos();
            $content = rtrim($handbook->getContent());
            $fallbackPhotoMarkdown = [];

            foreach ($uploadedFiles as $index => $file) {
                $token = $photoTokens[$index] ?? null;
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    if (is_string($token) && $token !== '') {
                        $content = $this->removePlaceholderMarkdown($content, $token);
                    }
                    $this->addFlash('warning', $translator->trans('handbook.photo_upload_failed'));
                    continue;
                }
                if ($file->getSize() > self::MAX_PHOTO_SIZE) {
                    if (is_string($token) && $token !== '') {
                        $content = $this->removePlaceholderMarkdown($content, $token);
                    }
                    $this->addFlash('warning', $translator->trans('handbook.photo_too_large'));
                    continue;
                }
                if (!$uploader->isAllowedRasterImage($file)) {
                    if (is_string($token) && $token !== '') {
                        $content = $this->removePlaceholderMarkdown($content, $token);
                    }
                    $this->addFlash('warning', $translator->trans('handbook.photo_invalid_type'));
                    continue;
                }

                try {
                    $filename = $uploader->uploadMessageAttachment($file, self::PHOTO_FOLDER . '/' . $car->getId());
                } catch (\RuntimeException) {
                    if (is_string($token) && $token !== '') {
                        $content = $this->removePlaceholderMarkdown($content, $token);
                    }
                    $this->addFlash('warning', $translator->trans('handbook.photo_upload_failed'));
                    continue;
                }

                $storedPath = $uploader->findMessageAttachmentPath($filename, self::PHOTO_FOLDER . '/' . $car->getId());
                if ($storedPath === null) {
                    if (is_string($token) && $token !== '') {
                        $content = $this->removePlaceholderMarkdown($content, $token);
                    }
                    $this->addFlash('warning', $translator->trans('handbook.photo_upload_failed'));
                    continue;
                }

                $photoFilenames[] = $filename;
                $altText = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'photo';
                $attachmentUrl = $this->generateUrl('app_car_handbook_attachment_show', [
                    'car' => $car->getId(),
                    'filename' => $filename,
                ]);
                $markdown = sprintf('![%s](%s)', $altText, $attachmentUrl);

                if (is_string($token) && $token !== '') {
                    $replacementCount = 0;
                    $content = str_replace(
                        self::PHOTO_PLACEHOLDER_PREFIX . $token,
                        $attachmentUrl,
                        $content,
                        $replacementCount
                    );

                    if ($replacementCount > 0) {
                        continue;
                    }
                }

                $fallbackPhotoMarkdown[] = $markdown;
            }

            if ($fallbackPhotoMarkdown !== []) {
                $content = trim($content . "\n\n" . implode("\n\n", $fallbackPhotoMarkdown));
            }

            $handbook->setContent($content);
            $handbook->setPhotos($photoFilenames);

            $em->persist($handbook);
            $em->flush();

            $this->addFlash('success', $translator->trans($isNew ? 'handbook.created' : 'handbook.updated'));

            return $this->redirectToRoute('app_car_handbook_show', ['car' => $car->getId()]);
        }

        return $this->render('admin/car/handbook.html.twig', [
            'car' => $car,
            'handbook' => $handbook,
            'canManageHandbook' => true,
            'showEditor' => true,
            'handbookForm' => $form->createView(),
        ]);
    }

    private function removePlaceholderMarkdown(string $content, string $token): string
    {
        $pattern = sprintf(
            '/(?:\R){0,2}!\[[^\]]*]\(%s\)(?:\R)?/',
            preg_quote(self::PHOTO_PLACEHOLDER_PREFIX . $token, '/')
        );

        $updatedContent = preg_replace($pattern, '', $content);

        if ($updatedContent === null) {
            return $content;
        }

        return rtrim((string) preg_replace("/(\R){3,}/", "\n\n", $updatedContent));
    }

    #[Route('/admin/car/{car}/handbook/delete', name: 'app_car_handbook_delete', methods: ['POST'])]
    public function delete(
        Car $car,
        Request $request,
        EntityManagerInterface $em,
        ActiveCarService $activeCarService,
        FileUploaderService $uploader,
        TranslatorInterface $translator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$car->hasUser($user)) {
            throw $this->createAccessDeniedException();
        }
        if (!$car->canManageHandbook($user)) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('handbook_delete_' . $car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $activeCarService->setActiveCar($car);

        $handbook = $car->getHandbook();
        if ($handbook !== null) {
            foreach ($handbook->getPhotos() as $filename) {
                $uploader->deleteMessageAttachment($filename, self::PHOTO_FOLDER . '/' . $car->getId());
            }

            $em->remove($handbook);
            $em->flush();
            $this->addFlash('success', $translator->trans('handbook.deleted'));
        }

        return $this->redirectToRoute('app_car_show');
    }
}
