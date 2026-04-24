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
    protected static int    $otherCarId;
    protected static int    $otherUserId;
    protected static int    $userId;
    protected static string $token;
    protected static string $refreshToken;

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

        $otherCar = new Car();
        $otherCar->setName('Other Test Car');
        $otherCar->setMileage(20000);
        $otherCar->setMilageUnit('km');
        $em->persist($otherCar);

        $otherUser = new User();
        $otherUser->setEmail(static::otherTestEmail());
        $otherUser->setName('Other API Test User');
        $otherUser->setLocale('en');
        $otherUser->setIsVerified(true);
        $otherUser->setNotifiedOnEvents(false);
        $otherUser->setNotifiedOnOwnEvents(false);
        $otherUser->setPassword($hasher->hashPassword($otherUser, static::testPassword()));
        $em->persist($otherUser);

        $otherGroup = new UserType();
        $otherGroup->setName('Other Members');
        $otherGroup->setPricePerUnit(0.2);
        $otherGroup->setCar($otherCar);
        $otherGroup->addUser($otherUser);
        $em->persist($otherGroup);

        $em->flush();

        $auth = static::login(static::testEmail(), static::testPassword());

        static::$carId = $car->getId();
        static::$otherCarId = $otherCar->getId();
        static::$otherUserId = $otherUser->getId();
        static::$userId = $user->getId();
        static::$token = $auth['token'];
        static::$refreshToken = $auth['refresh_token'];
    }

    public static function tearDownAfterClass(): void
    {
        static::purgeFixtures(static::em());
        parent::tearDownAfterClass();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected static function testEmail(): string    { return 'api-test@test.local'; }
    protected static function otherTestEmail(): string { return 'api-test-other@test.local'; }
    protected static function testPassword(): string { return 'Test1234!'; }
    protected static function carIri(): string       { return '/api/cars/'  . static::$carId; }
    protected static function otherCarIri(): string  { return '/api/cars/'  . static::$otherCarId; }
    protected static function userIri(): string      { return '/api/users/' . static::$userId; }
    protected static function otherUserIri(): string { return '/api/users/' . static::$otherUserId; }

    /** Returns an HTTP client with the Bearer token pre-set. */
    protected static function authClient(?string $token = null): Client
    {
        return static::createClient([], [
            'headers' => ['Authorization' => 'Bearer ' . ($token ?? static::$token)],
        ]);
    }

    /** Obtains access and refresh tokens via POST /api/login. */
    protected static function login(string $email, string $password, ?string $deviceName = null): array
    {
        $payload = ['email' => $email, 'password' => $password];
        if (null !== $deviceName) {
            $payload['device_name'] = $deviceName;
        }

        $response = static::createClient()->request('POST', '/api/login', [
            'json' => $payload,
        ]);

        return $response->toArray();
    }

    protected static function refresh(string $refreshToken): array
    {
        $response = static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $refreshToken],
        ]);

        return $response->toArray();
    }

    protected static function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    // ── Fixture cleanup ───────────────────────────────────────────────────────

    private static function purgeFixtures(EntityManagerInterface $em): void
    {
        $em->clear();

        foreach ([static::testEmail(), static::otherTestEmail()] as $email) {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                continue;
            }

            foreach ($user->getUserTypes() as $ut) {
                $ut->removeUser($user);
            }
            $em->flush();

            $car = $user->getCar();
            if ($car) {
                $em->remove($car);
                $em->flush();
            }

            $em->remove($user);
            $em->flush();
        }
    }
}
