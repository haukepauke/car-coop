<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Car;
use App\Entity\User;
use App\Entity\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for all API tests.
 *
 * Provides one shared test user + car pair per test class, created in
 * setUpBeforeClass() and destroyed in tearDownAfterClass().
 * Individual tests call authClient() to get a pre-authenticated HTTP client.
 */
abstract class ApiTestCase extends BaseApiTestCase
{
    protected static int    $carId;
    protected static int    $userId;
    protected static string $token;

    // ── Fixture lifecycle ─────────────────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Boot the kernel so the container is available.
        static::createClient();

        $em     = static::em();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        static::purgeFixtures($em);

        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(10000);
        $car->setMilageUnit('km');
        $em->persist($car);

        $user = new User();
        $user->setEmail(static::testEmail());
        $user->setName('API Test User');
        $user->setLocale('en');
        $user->setIsVerified(true);
        $user->setNotifiedOnEvents(false);
        $user->setNotifiedOnOwnEvents(false);
        $user->setPassword($hasher->hashPassword($user, static::testPassword()));
        $em->persist($user);

        $group = new UserType();
        $group->setName('Members');
        $group->setPricePerUnit(0.2);
        $group->setCar($car);
        $group->addUser($user);
        $em->persist($group);

        $em->flush();

        static::$carId  = $car->getId();
        static::$userId = $user->getId();
        static::$token  = static::login(static::testEmail(), static::testPassword());
    }

    public static function tearDownAfterClass(): void
    {
        static::purgeFixtures(static::em());
        parent::tearDownAfterClass();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected static function testEmail(): string    { return 'api-test@test.local'; }
    protected static function testPassword(): string { return 'Test1234!'; }
    protected static function carIri(): string       { return '/api/cars/'  . static::$carId; }
    protected static function userIri(): string      { return '/api/users/' . static::$userId; }

    /** Returns an HTTP client with the Bearer token pre-set. */
    protected static function authClient(): Client
    {
        return static::createClient([], [
            'headers' => ['Authorization' => 'Bearer ' . static::$token],
        ]);
    }

    /** Obtains a JWT token via POST /api/login. */
    protected static function login(string $email, string $password): string
    {
        $response = static::createClient()->request('POST', '/api/login', [
            'json' => ['email' => $email, 'password' => $password],
        ]);

        return $response->toArray()['token'];
    }

    protected static function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    // ── Fixture cleanup ───────────────────────────────────────────────────────

    private static function purgeFixtures(EntityManagerInterface $em): void
    {
        $em->clear();

        $user = $em->getRepository(User::class)->findOneBy(['email' => static::testEmail()]);
        if (!$user) {
            return;
        }

        // Detach from all user types so the ManyToMany join rows are cleared.
        foreach ($user->getUserTypes() as $ut) {
            $ut->removeUser($user);
        }
        $em->flush();

        // Deleting the car cascades to trips, bookings, expenses, payments,
        // parking locations and messages (orphanRemoval / onDelete CASCADE).
        $car = $user->getCar();
        if ($car) {
            $em->remove($car);
            $em->flush();
        }

        $em->remove($user);
        $em->flush();
    }
}
