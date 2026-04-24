<?php

namespace App\Tests\Api;

use App\Entity\Message;

class MessageTest extends ApiTestCase
{
    protected static int $messageId;
    protected static string $attachmentFilename = 'manual.pdf';

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
        $message->setPhotos([static::$attachmentFilename]);
        $em->persist($message);
        $em->flush();

        $attachmentDirectory = static::getContainer()->getParameter('message_attachment_directory') . '/messages';
        if (!is_dir($attachmentDirectory)) {
            mkdir($attachmentDirectory, 0777, true);
        }
        file_put_contents($attachmentDirectory . '/' . static::$attachmentFilename, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

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
        $this->assertSame([
            '/api/messages/' . static::$messageId . '/attachments/' . rawurlencode(static::$attachmentFilename),
        ], $data['attachmentUrls']);
        $this->assertFalse($data['isSticky']);
    }

    public function testAttachmentDownloadReturns200ForAuthorizedUser(): void
    {
        $client = static::authClient();
        $client->request('GET', '/api/messages/' . static::$messageId . '/attachments/' . rawurlencode(static::$attachmentFilename));

        $this->assertResponseIsSuccessful();
        $headers = $client->getResponse()->getHeaders(false);
        $this->assertSame('application/pdf', $headers['content-type'][0] ?? null);
        $this->assertStringContainsString('inline', $headers['content-disposition'][0] ?? '');
    }

    public function testAttachmentDownloadUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/messages/' . static::$messageId . '/attachments/' . rawurlencode(static::$attachmentFilename));
        $this->assertResponseStatusCodeSame(401);
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
