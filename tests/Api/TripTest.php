<?php

namespace App\Tests\Api;

use App\Entity\Trip;

class TripTest extends ApiTestCase
{
    protected static int $tripId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em   = static::em();
        $car  = $em->find(\App\Entity\Car::class,  static::$carId);
        $user = $em->find(\App\Entity\User::class, static::$userId);

        $trip = new Trip();
        $trip->setStartMileage(10000);
        $trip->setEndMileage(10300);
        $trip->setStartDate(new \DateTime('2024-01-01'));
        $trip->setEndDate(new \DateTime('2024-01-07'));
        $trip->setType('vacation');
        $trip->setCar($car);
        $trip->addUser($user);
        $trip->setEditor($user);
        $em->persist($trip);
        $em->flush();

        static::$tripId = $trip->getId();
    }

    private function tripIri(): string
    {
        return '/api/trips/' . static::$tripId;
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/trips');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', $this->tripIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$tripId, $data['id']);
        $this->assertSame(10000, $data['startMileage']);
        $this->assertSame(10300, $data['endMileage']);
        $this->assertSame('vacation', $data['type']);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/trips');
        $this->assertResponseStatusCodeSame(401);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function testPostCreatesTrip(): void
    {
        $response = static::authClient()->request('POST', '/api/trips', [
            'json' => [
                'startMileage' => 20000,
                'endMileage'   => 20250,
                'startDate'    => '2024-06-01',
                'endDate'      => '2024-06-03',
                'type'         => 'transport',
                'car'          => static::carIri(),
                'users'        => [static::userIri()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame(20000, $data['startMileage']);
        $this->assertSame('transport', $data['type']);
        $this->assertArrayHasKey('editor', $data);
    }

    public function testPostUnauthenticatedReturns401(): void
    {
        static::createClient()->request('POST', '/api/trips', [
            'json' => [
                'startMileage' => 1000,
                'endMileage'   => 1100,
                'startDate'    => '2024-06-01',
                'endDate'      => '2024-06-03',
                'type'         => 'transport',
                'car'          => static::carIri(),
                'users'        => [static::userIri()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────

    public function testPutUpdatesTrip(): void
    {
        $response = static::authClient()->request('PUT', $this->tripIri(), [
            'json' => [
                'startMileage' => 10000,
                'endMileage'   => 10500,
                'startDate'    => '2024-01-01',
                'endDate'      => '2024-01-10',
                'type'         => 'service_free',
                'car'          => static::carIri(),
                'users'        => [static::userIri()],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(10500, $data['endMileage']);
        $this->assertSame('service_free', $data['type']);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function testDeleteTrip(): void
    {
        static::authClient()->request('DELETE', $this->tripIri());
        $this->assertResponseStatusCodeSame(204);
    }
}
