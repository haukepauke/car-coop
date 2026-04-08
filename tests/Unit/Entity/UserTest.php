<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Car;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\UserType;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Alice');
        $user->setLocale('en');
        $user->setPassword('hashed');
        return $user;
    }

    private function makeCar(): Car
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(0);
        $car->setMilageUnit('km');
        return $car;
    }

    private function makeExpense(float $amount, string $date, Car $car = null): Expense
    {
        $e = new Expense();
        $e->setName('Fuel');
        $e->setType('fuel');
        $e->setAmount($amount);
        $e->setDate(new \DateTime($date));
        if ($car !== null) {
            $e->setCar($car);
        }
        return $e;
    }

    private function makePayment(float $amount, string $date, Car $car = null): Payment
    {
        $p = new Payment();
        $p->setAmount($amount);
        $p->setDate(new \DateTime($date));
        $p->setType('cash');
        if ($car !== null) {
            $p->setCar($car);
        }
        return $p;
    }

    private function makeCompletedTrip(int $startMileage, int $endMileage, float $costs, Car $car = null): Trip
    {
        $trip = new Trip();
        $trip->setStartMileage($startMileage);
        $trip->setEndMileage($endMileage);
        $trip->setStartDate(new \DateTime('2024-01-01'));
        $trip->setEndDate(new \DateTime('2024-01-10'));
        $trip->setType('vacation');
        $trip->setCosts($costs);
        if ($car !== null) {
            $trip->setCar($car);
        }
        return $trip;
    }

    // ── getRoles() ────────────────────────────────────────────────────────────

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = $this->makeUser();
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testGetRolesDeduplicatesRoleUser(): void
    {
        $user = $this->makeUser();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $roles = $user->getRoles();
        $this->assertCount(array_unique($roles) === $roles ? count($roles) : count(array_unique($roles)), array_unique($roles));
        $this->assertSame(array_unique($roles), $roles);
    }

    public function testGetRolesIncludesExtraRoles(): void
    {
        $user = $this->makeUser();
        $user->setRoles(['ROLE_ADMIN']);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    // ── getUserIdentifier() ───────────────────────────────────────────────────

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = $this->makeUser();
        $this->assertSame('test@example.com', $user->getUserIdentifier());
    }

    // ── getExpensesByPeriod() ─────────────────────────────────────────────────

    public function testGetExpensesByPeriodReturnsExpensesInRange(): void
    {
        $user = $this->makeUser();
        $e1   = $this->makeExpense(30.0, '2024-03-01');
        $e2   = $this->makeExpense(20.0, '2024-05-01');
        $user->addExpense($e1);
        $user->addExpense($e2);

        $result = $user->getExpensesByPeriod(new \DateTime('2024-01-01'), new \DateTime('2024-04-01'));

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($e1));
    }

    public function testGetExpensesByPeriodIncludesBoundaryDates(): void
    {
        $user = $this->makeUser();
        $e    = $this->makeExpense(10.0, '2024-06-01');
        $user->addExpense($e);

        $result = $user->getExpensesByPeriod(new \DateTime('2024-06-01'), new \DateTime('2024-06-01'));

        $this->assertCount(1, $result);
    }

    // ── getMoneySpent() ───────────────────────────────────────────────────────

    public function testGetMoneySpentSumsExpensesAndPaymentsMade(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser();
        $user->addExpense($this->makeExpense(50.0, '2024-03-01', $car));

        $payment = $this->makePayment(20.0, '2024-04-01', $car);
        $payment->setFromUser($user);
        $user->addPaymentsMade($payment);

        $spent = $user->getMoneySpent($car, new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->assertEquals(70, $spent);
    }

    public function testGetMoneySpentSubtractsPaymentsReceived(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser();
        $user->addExpense($this->makeExpense(100.0, '2024-03-01', $car));

        $received = $this->makePayment(30.0, '2024-04-01', $car);
        $received->setToUser($user);
        $user->addPaymentsReceived($received);

        $spent = $user->getMoneySpent($car, new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->assertEquals(70, $spent);
    }

    // ── getTripMileage() ──────────────────────────────────────────────────────

    public function testGetTripMileageSumsCompletedTrips(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser();
        $trip = $this->makeCompletedTrip(10000, 10200, 0.0, $car);
        $trip->addUser($user);
        $user->addTrip($trip);

        $mileage = $user->getTripMileage($car, new \DateTime('2023-12-31'), new \DateTime('2025-01-01'));

        $this->assertSame(200, $mileage);
    }

    public function testGetTripMileageDividesByNumberOfUsers(): void
    {
        $car   = $this->makeCar();
        $user1 = $this->makeUser();
        $user2 = new User();
        $user2->setEmail('b@test.com');
        $user2->setName('Bob');
        $user2->setLocale('en');
        $user2->setPassword('hashed');

        $trip = $this->makeCompletedTrip(0, 100, 0.0, $car);
        $trip->addUser($user1);
        $trip->addUser($user2);
        $user1->addTrip($trip);

        $mileage = $user1->getTripMileage($car, new \DateTime('2023-12-31'), new \DateTime('2025-01-01'));

        $this->assertSame(50, $mileage); // 100 / 2 users
    }

    public function testGetTripMileageIgnoresIncompleteTrips(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser();
        $trip = new Trip();
        $trip->setCar($car);
        $trip->setStartMileage(0);
        $trip->setEndMileage(200);
        $trip->setStartDate(new \DateTime('2024-02-01'));
        $trip->setType('vacation');
        // no endDate → not completed
        $trip->addUser($user);
        $user->addTrip($trip);

        $mileage = $user->getTripMileage($car, new \DateTime('2023-12-31'), new \DateTime('2025-01-01'));

        $this->assertSame(0, $mileage);
    }

    // ── getBalance() ──────────────────────────────────────────────────────────

    public function testGetBalanceAddsExpensesAndSubtractsTripCosts(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser();

        $trip = $this->makeCompletedTrip(0, 100, 40.0, $car); // costs 40, split between 1 user
        $trip->addUser($user);
        $user->addTrip($trip);

        $user->addExpense($this->makeExpense(100.0, '2024-03-01', $car));

        // balance = 100 (expense) - 40 (trip costs / 1 user) = 60
        $this->assertEquals(60.0, $user->getBalance($car));
    }

    public function testGetBalanceAddsMadePayments(): void
    {
        $car     = $this->makeCar();
        $user    = $this->makeUser();
        $payment = $this->makePayment(25.0, '2024-04-01', $car);
        $payment->setFromUser($user);
        $user->addPaymentsMade($payment);

        $this->assertEquals(25.0, $user->getBalance($car));
    }

    public function testGetBalanceSubtractsReceivedPayments(): void
    {
        $car      = $this->makeCar();
        $user     = $this->makeUser();
        $received = $this->makePayment(15.0, '2024-04-01', $car);
        $received->setToUser($user);
        $user->addPaymentsReceived($received);

        $this->assertEquals(-15.0, $user->getBalance($car));
    }

    public function testGetBalanceIgnoresOtherCars(): void
    {
        $car1 = $this->makeCar();
        $car2 = $this->makeCar();
        $user = $this->makeUser();

        $user->addExpense($this->makeExpense(100.0, '2024-03-01', $car2));

        $this->assertEquals(0.0, $user->getBalance($car1));
    }

    // ── hasEntries() ──────────────────────────────────────────────────────────

    public function testHasEntriesReturnsFalseForNewUser(): void
    {
        $user = $this->makeUser();
        $this->assertFalse($user->hasEntries());
    }

    public function testHasEntriesReturnsTrueWhenUserHasExpenses(): void
    {
        $user = $this->makeUser();
        $user->addExpense($this->makeExpense(10.0, '2024-01-01'));
        $this->assertTrue($user->hasEntries());
    }

    public function testHasEntriesReturnsTrueWhenUserHasTrips(): void
    {
        $user = $this->makeUser();
        $trip = $this->makeCompletedTrip(0, 100, 0.0);
        $trip->addUser($user);
        $user->addTrip($trip);
        $this->assertTrue($user->hasEntries());
    }

    // ── getCars() ─────────────────────────────────────────────────────────────

    public function testGetCarsReturnsAllCarsWithoutDuplicates(): void
    {
        $user = $this->makeUser();
        $car  = new Car();
        $car->setName('My Car');
        $car->setMileage(0);
        $car->setMilageUnit('km');

        $ut1 = new UserType();
        $ut1->setName('Members');
        $ut1->setPricePerUnit(0.2);
        $ut1->setCar($car);
        $ut1->addUser($user);

        $ut2 = new UserType();
        $ut2->setName('Admins');
        $ut2->setPricePerUnit(0.2);
        $ut2->setCar($car);
        $ut2->addUser($user);

        $user->addUserType($ut1);
        $user->addUserType($ut2);

        // Same car in two userTypes → should appear only once
        $cars = $user->getCars();
        $this->assertCount(1, $cars);
        $this->assertSame($car, $cars[0]);
    }

    // ── isActive() ────────────────────────────────────────────────────────────

    public function testIsActiveReturnsTrueWhenUserHasActiveUserType(): void
    {
        $user = $this->makeUser();
        $ut   = new UserType();
        $ut->setName('Active');
        $ut->setPricePerUnit(0.2);
        $ut->setActive(true);
        $user->addUserType($ut);

        $this->assertTrue($user->isActive());
    }

    public function testIsActiveReturnsFalseWhenFirstUserTypeIsInactive(): void
    {
        $user = $this->makeUser();
        $ut   = new UserType();
        $ut->setName('Inactive');
        $ut->setPricePerUnit(0.2);
        $ut->setActive(false);
        $user->addUserType($ut);

        $this->assertFalse($user->isActive());
    }

    public function testIsActiveReturnsTrueWhenUserHasNoUserTypes(): void
    {
        $user = $this->makeUser();
        $this->assertTrue($user->isActive());
    }

    // ── anonymize() ───────────────────────────────────────────────────────────

    public function testAnonymizeMasksEmailKeepingFirstThreeChars(): void
    {
        $user = $this->makeUser(); // email: test@example.com

        $user->anonymize();

        // str_replace replaces ALL occurrences: substr('test', 3) = 't', which appears
        // at positions 0 and 3, so both are replaced → 'xesx'
        // 'example' → substr('example', 1) = 'xample' → replaced with 'xxxxxx' → 'exxxxxx'
        $this->assertSame('xesx@exxxxxx.com', $user->getEmail());
    }

    public function testAnonymizeMasksNameKeepingFirstChar(): void
    {
        $user = $this->makeUser(); // name: Alice

        $user->anonymize();

        // 'Alice' → keeps 'A', 'lice' → 'xxxx': 'Axxxx'
        $this->assertSame('Axxxx', $user->getName());
    }
}
