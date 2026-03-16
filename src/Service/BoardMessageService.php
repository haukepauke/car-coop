<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;

class BoardMessageService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @param array<string, string> $params HTML-escaped plain-text values keyed by %placeholder%
     */
    public function createSystemMessage(Car $car, string $translationKey, array $params = []): void
    {
        $message = new Message();
        $message->setCar($car);
        $message->setContent(json_encode(['key' => $translationKey, 'params' => $params]));
        // author stays null — marks this as a system-generated message

        $this->em->persist($message);
        $this->em->flush();
    }
}
