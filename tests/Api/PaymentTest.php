<?php

namespace App\Tests\Api;

use App\Entity\Car;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\UserType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Payment requires two different users (fromUser and toUser).
 * This test class creates a second user in addition to the shared fixture user.
 */
class PaymentTest extends ApiTestCase
{
    protected static int    $paymentId;
    protected static int    $userId2;

    protected static function user2Iri(): string
    {
        return '/api/users/' . static::$userId2;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em     = static::em();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $car    = $em->find(Car::class,  static::$carId);
        $user   = $em->find(User::class, static::$userId);

        // Create a second user so fromUser !== toUser
        $user2 = new User();
        $user2->setEmail('api-test-2@test.local');
        $user2->setName('API Test User 2');
        $user2->setLocale('en');
        $user2->setIsVerified(true);
        $user2->setNotifiedOnEvents(false);
        $user2->setNotifiedOnOwnEvents(false);
        $user2->setPassword($hasher->hashPassword($user2, static::testPassword()));
        $em->persist($user2);

        $group2 = new UserType();
        $group2->setName('Members 2');
        $group2->setPricePerUnit(0.2);
        $group2->setCar($car);
        $group2->addUser($user2);
        $em->persist($group2);

        $payment = new Payment();
        $payment->setDate(new \DateTime('2024-05-01'));
        $payment->setAmount(25.00);
        $payment->setType('cash');
        $payment->setFromUser($user);
        $payment->setToUser($user2);
        $payment->setCar($car);
        $em->persist($payment);

        $em->flush();

        static::$userId2   = $user2->getId();
        static::$paymentId = $payment->getId();
    }

    public static function tearDownAfterClass(): void
    {
        $em    = static::em();
        $user2 = $em->getRepository(User::class)->find(static::$userId2);
        if ($user2) {
            foreach ($user2->getUserTypes() as $ut) {
                $ut->removeUser($user2);
            }
            $em->flush();
            $em->remove($user2);
            $em->flush();
        }

        parent::tearDownAfterClass();
    }

    private function paymentIri(): string
    {
        return '/api/payments/' . static::$paymentId;
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/payments');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', $this->paymentIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$paymentId, $data['id']);
        $this->assertSame('cash', $data['type']);
        $this->assertEquals(25.0, $data['amount']);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/payments');
        $this->assertResponseStatusCodeSame(401);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function testPostCreatesPayment(): void
    {
        $response = static::authClient()->request('POST', '/api/payments', [
            'json' => [
                'date'     => '2024-06-01',
                'amount'   => 50.00,
                'type'     => 'banktransfer',
                'fromUser' => static::userIri(),
                'toUser'   => static::user2Iri(),
                'car'      => static::carIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('banktransfer', $data['type']);
        $this->assertEquals(50.0, $data['amount']);
    }

    public function testPostUnauthenticatedReturns401(): void
    {
        static::createClient()->request('POST', '/api/payments', [
            'json' => [
                'date'     => '2024-06-01',
                'amount'   => 10.00,
                'type'     => 'cash',
                'fromUser' => static::userIri(),
                'toUser'   => static::user2Iri(),
                'car'      => static::carIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────

    public function testPutUpdatesPayment(): void
    {
        $response = static::authClient()->request('PUT', $this->paymentIri(), [
            'json' => [
                'date'     => '2024-05-15',
                'amount'   => 30.00,
                'type'     => 'paypal',
                'fromUser' => static::userIri(),
                'toUser'   => static::user2Iri(),
                'car'      => static::carIri(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('paypal', $data['type']);
        $this->assertEquals(30.0, $data['amount']);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function testDeletePayment(): void
    {
        static::authClient()->request('DELETE', $this->paymentIri());
        $this->assertResponseStatusCodeSame(204);
    }
}
