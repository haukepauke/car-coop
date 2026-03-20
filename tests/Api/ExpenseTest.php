<?php

namespace App\Tests\Api;

use App\Entity\Expense;

class ExpenseTest extends ApiTestCase
{
    protected static int $expenseId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em   = static::em();
        $car  = $em->find(\App\Entity\Car::class,  static::$carId);
        $user = $em->find(\App\Entity\User::class, static::$userId);

        $expense = new Expense();
        $expense->setType('fuel');
        $expense->setName('Test fuel');
        $expense->setAmount(45.50);
        $expense->setDate(new \DateTime('2024-03-01'));
        $expense->setCar($car);
        $expense->setUser($user);
        $expense->setEditor($user);
        $em->persist($expense);
        $em->flush();

        static::$expenseId = $expense->getId();
    }

    private function expenseIri(): string
    {
        return '/api/expenses/' . static::$expenseId;
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/expenses');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', $this->expenseIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$expenseId, $data['id']);
        $this->assertSame('fuel', $data['type']);
        $this->assertSame('Test fuel', $data['name']);
        $this->assertSame(45.5, $data['amount']);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/expenses');
        $this->assertResponseStatusCodeSame(401);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function testPostCreatesExpense(): void
    {
        $response = static::authClient()->request('POST', '/api/expenses', [
            'json' => [
                'type'   => 'service',
                'name'   => 'Oil change',
                'amount' => 89.99,
                'date'   => '2024-04-15',
                'car'    => static::carIri(),
                'user'   => static::userIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('service', $data['type']);
        $this->assertSame('Oil change', $data['name']);
        $this->assertArrayHasKey('editor', $data);
    }

    public function testPostUnauthenticatedReturns401(): void
    {
        static::createClient()->request('POST', '/api/expenses', [
            'json' => [
                'type'   => 'fuel',
                'name'   => 'Fuel',
                'amount' => 50.0,
                'date'   => '2024-04-01',
                'car'    => static::carIri(),
                'user'   => static::userIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────

    public function testPutUpdatesExpense(): void
    {
        $response = static::authClient()->request('PUT', $this->expenseIri(), [
            'json' => [
                'type'   => 'other',
                'name'   => 'Parking fee',
                'amount' => 12.00,
                'date'   => '2024-03-15',
                'car'    => static::carIri(),
                'user'   => static::userIri(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('other', $data['type']);
        $this->assertSame('Parking fee', $data['name']);
        $this->assertEquals(12.0, $data['amount']);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function testDeleteExpense(): void
    {
        static::authClient()->request('DELETE', $this->expenseIri());
        $this->assertResponseStatusCodeSame(204);
    }
}
