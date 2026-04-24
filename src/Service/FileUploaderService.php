<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploaderService
{
    public const ALLOWED_RASTER_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    public const ALLOWED_RASTER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];
    public const ALLOWED_MESSAGE_ATTACHMENT_MIME_TYPES = self::ALLOWED_RASTER_MIME_TYPES + [
        'application/pdf' => 'pdf',
    ];
    public const ALLOWED_MESSAGE_ATTACHMENT_EXTENSIONS = [...self::ALLOWED_RASTER_EXTENSIONS, 'pdf'];

    public function __construct(
        private readonly string $targetDirectory,
        private readonly string $messageAttachmentDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function isAllowedRasterImage(UploadedFile $file): bool
    {
        return null !== $this->resolveAllowedExtension(
            $file,
            self::ALLOWED_RASTER_MIME_TYPES,
            self::ALLOWED_RASTER_EXTENSIONS,
        );
    }

    public function isAllowedMessageAttachment(UploadedFile $file): bool
    {
        return null !== $this->resolveAllowedExtension(
            $file,
            self::ALLOWED_MESSAGE_ATTACHMENT_MIME_TYPES,
            self::ALLOWED_MESSAGE_ATTACHMENT_EXTENSIONS,
        );
    }

    public function upload(UploadedFile $file, string $folder): string
    {
        $extension = $this->resolveAllowedExtension(
            $file,
            self::ALLOWED_RASTER_MIME_TYPES,
            self::ALLOWED_RASTER_EXTENSIONS,
        );
        if (null === $extension) {
            throw new \InvalidArgumentException('Only JPG, PNG, and GIF uploads are allowed.');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $extension;
        $directory = rtrim($this->getTargetDirectory(), '/') . '/' . trim($folder, '/');
        $this->ensureDirectoryExists($directory);

        try {
            $file->move($directory, $fileName);
        } catch (FileException $e) {
            throw new \RuntimeException(sprintf('Failed to store upload in "%s".', $directory), 0, $e);
        }

        return $fileName;
    }

    public function uploadMessageAttachment(UploadedFile $file, string $folder): string
    {
        $extension = $this->resolveAllowedExtension(
            $file,
            self::ALLOWED_MESSAGE_ATTACHMENT_MIME_TYPES,
            self::ALLOWED_MESSAGE_ATTACHMENT_EXTENSIONS,
        );
        if (null === $extension) {
            throw new \InvalidArgumentException('Only JPG, PNG, GIF, and PDF uploads are allowed.');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $extension;
        $directory = $this->getMessageAttachmentDirectory($folder);
        $this->ensureDirectoryExists($directory);

        try {
            $file->move($directory, $fileName);
        } catch (FileException $e) {
            throw new \RuntimeException(sprintf('Failed to store upload in "%s".', $directory), 0, $e);
        }

        return $fileName;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function getMessageAttachmentDirectory(string $folder = ''): string
    {
        if ($folder === '') {
            return $this->messageAttachmentDirectory;
        }

        return rtrim($this->messageAttachmentDirectory, '/') . '/' . trim($folder, '/');
    }

    public function findMessageAttachmentPath(string $filename, string $folder = ''): ?string
    {
        if (!$this->isSafeStoredFilename($filename)) {
            return null;
        }

        $directories = [
            $this->getMessageAttachmentDirectory($folder),
            $this->getTargetDirectory() . '/' . trim($folder, '/'),
        ];

        foreach ($directories as $directory) {
            $path = rtrim($directory, '/') . '/' . $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function deleteMessageAttachment(string $filename, string $folder = ''): void
    {
        if (!$this->isSafeStoredFilename($filename)) {
            return;
        }

        $directories = [
            $this->getMessageAttachmentDirectory($folder),
            $this->getTargetDirectory() . '/' . trim($folder, '/'),
        ];

        foreach ($directories as $directory) {
            $path = rtrim($directory, '/') . '/' . $filename;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function resolveAllowedExtension(UploadedFile $file, array $allowedMimeTypes, array $allowedExtensions): ?string
    {
        $mimeType = $file->getMimeType();
        if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType])) {
            return null;
        }

        $guessedExtension = strtolower((string) $file->guessExtension());
        if ($guessedExtension !== '' && !in_array($guessedExtension, $allowedExtensions, true)) {
            return null;
        }

        return $allowedMimeTypes[$mimeType];
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Failed to create upload directory "%s".', $directory));
            }
        }

        @chmod($directory, 0777);

        if (!is_writable($directory)) {
            throw new \RuntimeException(sprintf('Upload directory "%s" is not writable.', $directory));
        }
    }

    private function isSafeStoredFilename(string $filename): bool
    {
        return $filename !== '' && basename($filename) === $filename;
    }
}
