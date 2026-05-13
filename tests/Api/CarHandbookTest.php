<?php

namespace App\Tests\Api;

use App\Entity\Car;
use App\Entity\CarHandbook;

class CarHandbookTest extends ApiTestCase
{
    protected static int $handbookId;
    protected static int $otherHandbookId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em = static::em();
        $car = $em->find(Car::class, static::$carId);
        $otherCar = $em->find(Car::class, static::$otherCarId);

        $handbook = new CarHandbook();
        $handbook->setCar($car);
        $handbook->setContent("# API handbook\n\nImportant notes for this car.");
        $handbook->setPhotos(['guide-photo.jpg']);
        $em->persist($handbook);

        $otherHandbook = new CarHandbook();
        $otherHandbook->setCar($otherCar);
        $otherHandbook->setContent("# Other API handbook\n\nPrivate notes for another car.");
        $em->persist($otherHandbook);

        $em->flush();

        static::$handbookId = $handbook->getId();
        static::$otherHandbookId = $otherHandbook->getId();
    }

    private function handbookIri(): string
    {
        return '/api/car_handbooks/' . static::$handbookId;
    }

    private function otherHandbookIri(): string
    {
        return '/api/car_handbooks/' . static::$otherHandbookId;
    }

    public function testGetCollectionReturns200AndOwnHandbookOnly(): void
    {
        $response = static::authClient()->request('GET', '/api/car_handbooks');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $ids = array_column($data['member'], 'id');
        $this->assertContains(static::$handbookId, $ids);
        $this->assertNotContains(static::$otherHandbookId, $ids);
    }

    public function testGetItemReturns200WithReadableFields(): void
    {
        $response = static::authClient()->request('GET', $this->handbookIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertSame(static::$handbookId, $data['id']);
        $this->assertSame('# API handbook' . "\n\n" . 'Important notes for this car.', $data['content']);
        $this->assertSame(['/api/car_handbooks/' . static::$handbookId . '/attachments/guide-photo.jpg'], $data['attachmentUrls']);
        $this->assertSame(static::$carId, $data['car']['id']);
        $this->assertSame(static::carIri(), $data['car']['@id']);
    }

    public function testGetForeignItemReturns403(): void
    {
        static::authClient()->request('GET', $this->otherHandbookIri());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/car_handbooks');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetItemUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', $this->handbookIri());

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPostIsNotAllowed(): void
    {
        static::authClient()->request('POST', '/api/car_handbooks', [
            'json' => [
                'content' => '# Should not work',
                'car' => static::carIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(405);
    }

    public function testPutIsNotAllowed(): void
    {
        static::authClient()->request('PUT', $this->handbookIri(), [
            'json' => [
                'content' => '# Should not work',
                'car' => static::carIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(405);
    }

    public function testDeleteIsNotAllowed(): void
    {
        static::authClient()->request('DELETE', $this->handbookIri());

        $this->assertResponseStatusCodeSame(405);
    }
}
