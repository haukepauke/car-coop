<?php

namespace App\Tests\Scenario;

use App\Entity\Car;
use App\Entity\Invitation;
use App\Entity\User;
use App\Entity\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

abstract class ScenarioTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function createUser(
        string $email,
        string $password = 'ScenarioPass123!',
        bool $verified = true,
        string $name = 'Scenario User',
        string $locale = 'en',
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLocale($locale);
        $user->setIsVerified($verified);
        $user->setNotifiedOnEvents(false);
        $user->setNotifiedOnOwnEvents(false);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * @return array{0: Car, 1: UserType}
     */
    protected function createCarMembership(
        User $user,
        string $carName = 'Scenario Car',
        string $groupName = 'Crew',
    ): array {
        $car = new Car();
        $car->setName($carName);
        $car->setMileage(10000);
        $car->setMilageUnit('km');
        $car->setCurrency('EUR');
        $this->em()->persist($car);

        $group = new UserType();
        $group->setCar($car);
        $group->setName($groupName);
        $group->setPricePerUnit(0.20);
        $group->setAdmin(true);
        $group->setFixed(true);
        $group->addUser($user);
        $this->em()->persist($group);

        $retired = new UserType();
        $retired->setCar($car);
        $retired->setName('Retired');
        $retired->setPricePerUnit(0.0);
        $retired->setActive(false);
        $retired->setFixed(true);
        $retired->setAdmin(false);
        $this->em()->persist($retired);

        $this->em()->flush();

        return [$car, $group];
    }

    protected function createInvitation(User $inviter, UserType $userType, string $email, string $hash): Invitation
    {
        $invitation = new Invitation();
        $invitation->setCreatedBy($inviter);
        $invitation->setUserType($userType);
        $invitation->setEmail($email);
        $invitation->setHash($hash);
        $invitation->setStatus('new');
        $invitation->setCreatedAt(new \DateTimeImmutable());

        $this->em()->persist($invitation);
        $this->em()->flush();

        return $invitation;
    }

    protected function loginThroughForm(string $email, string $password, string $locale = 'en'): Crawler
    {
        $crawler = $this->client->request('GET', sprintf('/%s/login', $locale));
        $form = $crawler->filterXPath('//form')->form([
            'email' => $email,
            'password' => $password,
        ]);

        return $this->client->submit($form);
    }

    protected function ageRegistrationFormSession(): void
    {
        usleep(3_100_000);
    }

    protected function verificationPathFor(User $user): string
    {
        $helper = static::getContainer()->get(VerifyEmailHelperInterface::class);
        $signedUrl = $helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()]
        )->getSignedUrl();

        $parts = parse_url($signedUrl);
        $path = $parts['path'] ?? '/verify/email';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $path . $query;
    }

    protected function followRedirectChain(int $maxRedirects = 10): ?Crawler
    {
        $crawler = null;

        for ($i = 0; $i < $maxRedirects && $this->client->getResponse()->isRedirect(); ++$i) {
            $crawler = $this->client->followRedirect();
        }

        return $crawler;
    }

    protected function reloadUser(string $email): User
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    protected function resetDatabase(): void
    {
        $em = $this->em();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);

        if ($metadata !== []) {
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }

        $em->clear();
    }
}
