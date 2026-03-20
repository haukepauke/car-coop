<?php

namespace App\Tests\Api;

class UserTest extends ApiTestCase
{
    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(1, count($data['member']));
    }

    public function testGetCollectionContainsOwnUser(): void
    {
        $response = static::authClient()->request('GET', '/api/users');
        $data     = $response->toArray();

        $ids = array_column($data['member'], 'id');
        $this->assertContains(static::$userId, $ids);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', static::userIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$userId, $data['id']);
        $this->assertSame(static::testEmail(), $data['email']);
        $this->assertArrayHasKey('name', $data);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/users');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetItemUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', static::userIri());
        $this->assertResponseStatusCodeSame(401);
    }
}
