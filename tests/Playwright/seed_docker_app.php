<?php

use App\Entity\Car;
use App\Entity\Booking;
use App\Entity\Expense;
use App\Entity\Invitation;
use App\Entity\Message;
use App\Entity\Payment;
use App\Entity\Trip;
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

$member = $em->getRepository(User::class)->findOneBy(['email' => 'a11y-member@test.local']) ?? new User();
$member->setEmail('a11y-member@test.local');
$member->setName('Accessibility Member');
$member->setLocale('en');
$member->setThemePreference('light');
$member->setIsVerified(true);
$member->setNotifiedOnEvents(false);
$member->setNotifiedOnOwnEvents(false);
$member->setPassword(password_hash('ScenarioPass123!', PASSWORD_BCRYPT, ['cost' => 4]));
$em->persist($member);

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
$crew->addUser($member);
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

$expense = $em->getRepository(Expense::class)->findOneBy([
    'car' => $car,
    'name' => 'Playwright expense seed',
]) ?? new Expense();
$expense->setCar($car);
$expense->setUser($user);
$expense->setEditor($user);
$expense->setType('fuel');
$expense->setName('Playwright expense seed');
$expense->setComment('Seeded expense for edit-form regression coverage.');
$expense->setAmount(42.50);
$expense->setDate(new DateTime('2026-04-20'));
$em->persist($expense);

$payment = $em->getRepository(Payment::class)->findOneBy([
    'car' => $car,
    'comment' => 'Playwright payment seed',
]) ?? new Payment();
$payment->setCar($car);
$payment->setFromUser($user);
$payment->setToUser($member);
$payment->setDate(new DateTime('2026-04-21'));
$payment->setAmount(15.75);
$payment->setType('cash');
$payment->setComment('Playwright payment seed');
$em->persist($payment);

$trip = $em->getRepository(Trip::class)->findOneBy([
    'car' => $car,
    'comment' => 'Playwright trip seed',
]) ?? new Trip();
$trip->setCar($car);
$trip->setStartMileage(12345);
$trip->setEndMileage(12360);
$trip->setStartDate(new DateTime('2026-04-22'));
$trip->setEndDate(new DateTime('2026-04-22'));
$trip->setType('transport');
$trip->setComment('Playwright trip seed');
$trip->setEditor($user);
foreach ($trip->getUsers()->toArray() as $tripUser) {
    $trip->removeUser($tripUser);
}
$trip->addUser($user);
$em->persist($trip);

$booking = $em->getRepository(Booking::class)->findOneBy([
    'car' => $car,
    'title' => 'Playwright booking seed',
]) ?? new Booking();
$booking->setCar($car);
$booking->setUser($user);
$booking->setEditor($user);
$booking->setStatus('fixed');
$booking->setTitle('Playwright booking seed');
$booking->setStartDate(new DateTime('+5 days 10:00'));
$booking->setEndDate(new DateTime('+5 days 12:00'));
$em->persist($booking);

$em->flush();
$kernel->shutdown();
