<?php

namespace App\Tests\Api;

class CarTest extends ApiTestCase
{
    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/cars');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(1, count($data['member']));
    }

    public function testGetCollectionContainsOwnCar(): void
    {
        $response = static::authClient()->request('GET', '/api/cars');
        $data = $response->toArray();

        $ids = array_column($data['member'], 'id');
        $this->assertContains(static::$carId, $ids);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', static::carIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$carId, $data['id']);
        $this->assertArrayHasKey('name', $data);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/cars');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetItemUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', static::carIri());
        $this->assertResponseStatusCodeSame(401);
    }
}
