<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message;
use App\Service\FileUploaderService;

/**
 * Deletes associated photo files from disk before removing a Message entity.
 */
class MessageDeleteProcessor implements ProcessorInterface
{
    private const PHOTO_FOLDER = 'messages';

    public function __construct(
        private readonly ProcessorInterface $inner,
        private readonly FileUploaderService $uploader,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Message) {
            foreach ($data->getPhotos() as $filename) {
                $path = $this->uploader->getTargetDirectory()
                    . '/' . self::PHOTO_FOLDER
                    . '/' . $filename;
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}
