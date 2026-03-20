<?php

namespace App\Tests\Api;

use App\Entity\ParkingLocation;

class ParkingLocationTest extends ApiTestCase
{
    protected static int $parkingId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em   = static::em();
        $car  = $em->find(\App\Entity\Car::class,  static::$carId);
        $user = $em->find(\App\Entity\User::class, static::$userId);

        $parking = new ParkingLocation();
        $parking->setLatitude(52.5200);
        $parking->setLongitude(13.4050);
        $parking->setCar($car);
        $parking->setUser($user);
        $em->persist($parking);
        $em->flush();

        static::$parkingId = $parking->getId();
    }

    private function parkingIri(): string
    {
        return '/api/parking_locations/' . static::$parkingId;
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/parking_locations');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', $this->parkingIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$parkingId, $data['id']);
        $this->assertSame(52.52, $data['latitude']);
        $this->assertSame(13.405, $data['longitude']);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/parking_locations');
        $this->assertResponseStatusCodeSame(401);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function testPostCreatesLocation(): void
    {
        $response = static::authClient()->request('POST', '/api/parking_locations', [
            'json' => [
                'latitude'  => 48.1351,
                'longitude' => 11.5820,
                'car'       => static::carIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame(48.1351, $data['latitude']);
        $this->assertSame(11.582, $data['longitude']);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testPostUnauthenticatedReturns401(): void
    {
        static::createClient()->request('POST', '/api/parking_locations', [
            'json' => [
                'latitude'  => 48.0,
                'longitude' => 11.0,
                'car'       => static::carIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────

    public function testPutUpdatesLocation(): void
    {
        $response = static::authClient()->request('PUT', $this->parkingIri(), [
            'json' => [
                'latitude'  => 53.5753,
                'longitude' => 10.0153,
                'car'       => static::carIri(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(53.5753, $data['latitude']);
        $this->assertSame(10.0153, $data['longitude']);
    }

    // ── DELETE (not supported) ────────────────────────────────────────────────

    public function testDeleteReturns405(): void
    {
        static::authClient()->request('DELETE', $this->parkingIri());
        $this->assertResponseStatusCodeSame(405);
    }
}
