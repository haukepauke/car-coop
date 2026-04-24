<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Message;
use App\Entity\Car;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    // ── getPhotos() ───────────────────────────────────────────────────────────

    public function testGetPhotosReturnsEmptyArrayWhenNotSet(): void
    {
        $car = new Car();
        $car->setName('Test');
        $car->setMileage(0);
        $car->setMilageUnit('km');

        $message = new Message();
        $message->setCar($car);
        $message->setContent('Hello');

        $this->assertSame([], $message->getPhotos());
    }

    public function testGetPhotosReturnsSetArray(): void
    {
        $car = new Car();
        $car->setName('Test');
        $car->setMileage(0);
        $car->setMilageUnit('km');

        $message = new Message();
        $message->setCar($car);
        $message->setContent('With photos');
        $message->setPhotos(['photo1.jpg', 'photo2.jpg']);

        $this->assertSame(['photo1.jpg', 'photo2.jpg'], $message->getPhotos());
    }

    public function testSetPhotosWithEmptyArrayStoresNull(): void
    {
        $message = new Message();
        $message->setPhotos([]);
        $this->assertSame([], $message->getPhotos());
    }

    public function testHasPhotoMatchesStoredFilename(): void
    {
        $message = new Message();
        $message->setPhotos(['photo1.jpg', 'manual.pdf']);

        $this->assertTrue($message->hasPhoto('manual.pdf'));
        $this->assertFalse($message->hasPhoto('missing.jpg'));
    }

    public function testGetAttachmentUrlsReturnsApiPaths(): void
    {
        $message = new Message();
        $message->setPhotos(['manual.pdf', 'photo1.jpg']);

        $reflection = new \ReflectionProperty(Message::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($message, 42);

        $this->assertSame([
            '/api/messages/42/attachments/manual.pdf',
            '/api/messages/42/attachments/photo1.jpg',
        ], $message->getAttachmentUrls());
    }

    // ── getSystemData() ───────────────────────────────────────────────────────

    public function testGetSystemDataReturnsNullForUserMessage(): void
    {
        $car  = new Car();
        $car->setName('Test');
        $car->setMileage(0);
        $car->setMilageUnit('km');

        $user = new User();
        $user->setEmail('u@test.com');
        $user->setName('U');
        $user->setLocale('en');
        $user->setPassword('hashed');

        $message = new Message();
        $message->setCar($car);
        $message->setAuthor($user);
        $message->setContent('Regular user message');

        $this->assertNull($message->getSystemData());
    }

    public function testGetSystemDataReturnsNullForSystemMessageWithInvalidJson(): void
    {
        $car = new Car();
        $car->setName('Test');
        $car->setMileage(0);
        $car->setMilageUnit('km');

        $message = new Message();
        $message->setCar($car);
        $message->setContent('not json at all');
        // author = null → system message

        $this->assertNull($message->getSystemData());
    }

    public function testGetSystemDataReturnsNullWhenJsonHasNoKeyField(): void
    {
        $message = new Message();
        $message->setContent(json_encode(['foo' => 'bar']));

        $this->assertNull($message->getSystemData());
    }

    public function testGetSystemDataReturnsArrayForValidSystemMessage(): void
    {
        $message = new Message();
        $payload = ['key' => 'booking.created', 'params' => ['user' => 'Alice']];
        $message->setContent(json_encode($payload));
        // author remains null → system message

        $result = $message->getSystemData();

        $this->assertIsArray($result);
        $this->assertSame('booking.created', $result['key']);
        $this->assertSame(['user' => 'Alice'], $result['params']);
    }
}
