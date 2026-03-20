<?php

namespace App\Tests\Api;

use App\Entity\Message;

class MessageTest extends ApiTestCase
{
    protected static int $messageId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em   = static::em();
        $car  = $em->find(\App\Entity\Car::class,  static::$carId);
        $user = $em->find(\App\Entity\User::class, static::$userId);

        $message = new Message();
        $message->setCar($car);
        $message->setAuthor($user);
        $message->setContent('Hello from the test suite!');
        $em->persist($message);
        $em->flush();

        static::$messageId = $message->getId();
    }

    private function messageIri(): string
    {
        return '/api/messages/' . static::$messageId;
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/messages');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', $this->messageIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$messageId, $data['id']);
        $this->assertSame('Hello from the test suite!', $data['content']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertFalse($data['isSticky']);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/messages');
        $this->assertResponseStatusCodeSame(401);
    }

    // ── POST (multipart/form-data) ────────────────────────────────────────────

    public function testPostCreatesMessage(): void
    {
        $client = static::authClient();

        // The message endpoint uses multipart/form-data; send fields as form parameters.
        $client->request('POST', '/api/messages', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'Accept'       => 'application/json',
            ],
            'extra' => [
                'parameters' => [
                    'car'     => (string) static::$carId,
                    'content' => 'Sent via multipart POST',
                ],
                'files' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Sent via multipart POST', $data['content']);
        $this->assertArrayHasKey('author', $data);
    }

    public function testPostUnauthenticatedReturns401(): void
    {
        static::createClient()->request('POST', '/api/messages', [
            'headers' => ['Content-Type' => 'multipart/form-data', 'Accept' => 'application/json'],
            'extra'   => [
                'parameters' => ['car' => (string) static::$carId, 'content' => 'Unauth'],
                'files'      => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function testDeleteOwnMessageReturns204(): void
    {
        static::authClient()->request('DELETE', $this->messageIri());
        $this->assertResponseStatusCodeSame(204);
    }
}
