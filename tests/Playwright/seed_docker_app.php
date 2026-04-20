<?php

use App\Entity\Car;
use App\Entity\Invitation;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__, 2) . '/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
}

$kernel = new \App\Kernel($_SERVER['APP_ENV'] ?? 'dev', true);
$kernel->boot();

$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine.orm.entity_manager');

$user = $em->getRepository(User::class)->findOneBy(['email' => 'a11y-user@test.local']) ?? new User();
$user->setEmail('a11y-user@test.local');
$user->setName('Accessibility User');
$user->setLocale('en');
$user->setThemePreference('classic');
$user->setIsVerified(true);
$user->setNotifiedOnEvents(false);
$user->setNotifiedOnOwnEvents(false);
$user->setPassword(password_hash('ScenarioPass123!', PASSWORD_BCRYPT, ['cost' => 4]));
$em->persist($user);

$car = $em->getRepository(Car::class)->findOneBy(['name' => 'Accessibility Car']) ?? new Car();
$car->setName('Accessibility Car');
$car->setMileage(12345);
$car->setMilageUnit('km');
$car->setCurrency('EUR');
$em->persist($car);

$crew = $em->getRepository(UserType::class)->findOneBy(['car' => $car, 'name' => 'Crew']) ?? new UserType();
$crew->setCar($car);
$crew->setName('Crew');
$crew->setPricePerUnit(0.25);
$crew->setAdmin(true);
$crew->setFixed(true);
$crew->addUser($user);
$em->persist($crew);

$retired = $em->getRepository(UserType::class)->findOneBy(['car' => $car, 'name' => 'Retired']) ?? new UserType();
$retired->setCar($car);
$retired->setName('Retired');
$retired->setPricePerUnit(0.0);
$retired->setActive(false);
$retired->setFixed(true);
$retired->setAdmin(false);
$em->persist($retired);

$message = $em->getRepository(Message::class)->findOneBy([
    'car' => $car,
    'author' => $user,
    'content' => '<p>Accessibility smoke test message.</p>',
]) ?? new Message();
$message->setCar($car);
$message->setAuthor($user);
$message->setContent('<p>Accessibility smoke test message.</p>');
$message->setIsSticky(true);
$em->persist($message);

$invitation = $em->getRepository(Invitation::class)->findOneBy(['hash' => 'playwright-pending-invite']) ?? new Invitation();
$invitation->setCreatedBy($user);
$invitation->setUserType($crew);
$invitation->setEmail('pending-invite@test.local');
$invitation->setHash('playwright-pending-invite');
$invitation->setStatus('new');
$invitation->setCreatedAt(new DateTimeImmutable());
$em->persist($invitation);

$em->flush();
$kernel->shutdown();
