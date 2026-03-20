<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Car;
use App\Entity\Expense;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\UserType;
use PHPUnit\Framework\TestCase;

class CarTest extends TestCase
{
    /** Sets a private/protected id via reflection (ORM sets it in production). */
    private function setId(object $entity, int $id): void
    {
        $prop = new \ReflectionProperty($entity, 'id');
        $prop->setValue($entity, $id);
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $user->setEmail("user{$id}@test.com");
        $user->setName("User {$id}");
        $user->setLocale('en');
        $user->setPassword('hashed');
        $this->setId($user, $id);
        return $user;
    }

    private function makeCar(): Car
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(10000);
        $car->setMilageUnit('km');
        return $car;
    }

    private function makeUserType(Car $car, bool $active = true, bool $admin = false): UserType
    {
        $ut = new UserType();
        $ut->setName('Members');
        $ut->setPricePerUnit(0.2);
        $ut->setCar($car);
        $ut->setActive($active);
        $ut->setAdmin($admin);
        return $ut;
    }

    // ── hasUser() ─────────────────────────────────────────────────────────────

    public function testHasUserReturnsTrueForMember(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser(1);
        $ut   = $this->makeUserType($car);
        $ut->addUser($user);
        $car->addUserType($ut);

        $this->assertTrue($car->hasUser($user));
    }

    public function testHasUserReturnsFalseForNonMember(): void
    {
        $car      = $this->makeCar();
        $member   = $this->makeUser(1);
        $stranger = $this->makeUser(2);
        $ut       = $this->makeUserType($car);
        $ut->addUser($member);
        $car->addUserType($ut);

        $this->assertFalse($car->hasUser($stranger));
    }

    public function testHasUserReturnsFalseWhenNoUserTypes(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser(1);

        $this->assertFalse($car->hasUser($user));
    }

    // ── isAdminUser() ─────────────────────────────────────────────────────────

    public function testIsAdminUserReturnsTrueForAdminGroupMember(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser(1);
        $ut   = $this->makeUserType($car, true, true); // admin = true
        $ut->addUser($user);
        $car->addUserType($ut);

        $this->assertTrue($car->isAdminUser($user));
    }

    public function testIsAdminUserReturnsFalseForNonAdminGroupMember(): void
    {
        $car  = $this->makeCar();
        $user = $this->makeUser(1);
        $ut   = $this->makeUserType($car, true, false); // admin = false
        $ut->addUser($user);
        $car->addUserType($ut);

        $this->assertFalse($car->isAdminUser($user));
    }

    public function testIsAdminUserReturnsFalseForNonMember(): void
    {
        $car     = $this->makeCar();
        $admin   = $this->makeUser(1);
        $visitor = $this->makeUser(2);
        $ut      = $this->makeUserType($car, true, true);
        $ut->addUser($admin);
        $car->addUserType($ut);

        $this->assertFalse($car->isAdminUser($visitor));
    }

    // ── getUsers() ────────────────────────────────────────────────────────────

    public function testGetUsersReturnsAllUsersAcrossUserTypes(): void
    {
        $car   = $this->makeCar();
        $user1 = $this->makeUser(1);
        $user2 = $this->makeUser(2);
        $ut1   = $this->makeUserType($car);
        $ut2   = $this->makeUserType($car);
        $ut1->addUser($user1);
        $ut2->addUser($user2);
        $car->addUserType($ut1);
        $car->addUserType($ut2);

        $users = $car->getUsers();
        $this->assertCount(2, $users);
    }

    public function testGetUsersReturnsEmptyCollectionForCarWithNoUserTypes(): void
    {
        $car = $this->makeCar();
        $this->assertCount(0, $car->getUsers());
    }

    // ── getActiveUsers() ──────────────────────────────────────────────────────

    public function testGetActiveUsersFiltersOutInactiveUsers(): void
    {
        $car         = $this->makeCar();
        $activeUser  = $this->makeUser(1);
        $inactiveUser = $this->makeUser(2);

        $activeUt   = $this->makeUserType($car, true);
        $inactiveUt = $this->makeUserType($car, false);
        // addUserType sets both sides of the relationship; isActive() reads user->userTypes
        $activeUser->addUserType($activeUt);
        $inactiveUser->addUserType($inactiveUt);
        $car->addUserType($activeUt);
        $car->addUserType($inactiveUt);

        $activeUsers = $car->getActiveUsers();
        $this->assertCount(1, $activeUsers);
        $this->assertTrue($activeUsers->contains($activeUser));
        $this->assertFalse($activeUsers->contains($inactiveUser));
    }

    // ── getDistanceTravelled() ────────────────────────────────────────────────

    private function makeCompletedTrip(int $start, int $end, string $startDate, string $endDate): Trip
    {
        $trip = new Trip();
        $trip->setStartMileage($start);
        $trip->setEndMileage($end);
        $trip->setStartDate(new \DateTime($startDate));
        $trip->setEndDate(new \DateTime($endDate));
        $trip->setType('vacation');
        return $trip;
    }

    public function testGetDistanceTravelledSumsTripMileageInRange(): void
    {
        $car   = $this->makeCar();
        $trip1 = $this->makeCompletedTrip(10000, 10200, '2024-02-01', '2024-02-03');
        $trip2 = $this->makeCompletedTrip(10200, 10500, '2024-03-01', '2024-03-05');
        $car->addTrip($trip1);
        $car->addTrip($trip2);

        $distance = $car->getDistanceTravelled(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-12-31'),
        );

        $this->assertSame(500, $distance);
    }

    public function testGetDistanceTravelledIgnoresTripsOutsideRange(): void
    {
        $car  = $this->makeCar();
        $trip = $this->makeCompletedTrip(1000, 1200, '2023-05-01', '2023-05-03');
        $car->addTrip($trip);

        $distance = $car->getDistanceTravelled(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-12-31'),
        );

        $this->assertSame(0, $distance);
    }

    public function testGetDistanceTravelledIgnoresIncompleteTrips(): void
    {
        $car  = $this->makeCar();
        $trip = new Trip();
        $trip->setStartMileage(1000);
        $trip->setEndMileage(1200);
        $trip->setStartDate(new \DateTime('2024-02-01'));
        $trip->setType('vacation');
        // no endDate → not completed
        $car->addTrip($trip);

        $distance = $car->getDistanceTravelled(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-12-31'),
        );

        $this->assertSame(0, $distance);
    }

    // ── getMoneySpent() ───────────────────────────────────────────────────────

    private function makeExpense(float $amount, string $date, string $type = 'fuel'): Expense
    {
        $expense = new Expense();
        $expense->setName('Test expense');
        $expense->setAmount($amount);
        $expense->setDate(new \DateTime($date));
        $expense->setType($type);
        return $expense;
    }

    public function testGetMoneySpentSumsExpensesInDateRange(): void
    {
        $car = $this->makeCar();
        $car->addExpense($this->makeExpense(50.0, '2024-03-15'));
        $car->addExpense($this->makeExpense(30.0, '2024-04-10'));

        $total = $car->getMoneySpent(new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->assertEquals(80, $total);
    }

    public function testGetMoneySpentIgnoresExpensesOutsideRange(): void
    {
        $car = $this->makeCar();
        $car->addExpense($this->makeExpense(100.0, '2023-06-01'));

        $total = $car->getMoneySpent(new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->assertSame(0, $total);
    }

    public function testGetMoneySpentFiltersExpensesByType(): void
    {
        $car = $this->makeCar();
        $car->addExpense($this->makeExpense(60.0, '2024-03-01', 'fuel'));
        $car->addExpense($this->makeExpense(40.0, '2024-03-02', 'service'));

        $fuelOnly = $car->getMoneySpent(new \DateTime('2024-01-01'), new \DateTime('2024-12-31'), 'fuel');

        $this->assertEquals(60, $fuelOnly);
    }

    public function testGetMoneySpentWithDefaultDatesIncludesAllExpenses(): void
    {
        $car = $this->makeCar();
        $car->addExpense($this->makeExpense(25.0, '2022-01-01'));
        $car->addExpense($this->makeExpense(15.0, '2024-06-01'));

        $total = $car->getMoneySpent();

        $this->assertEquals(40, $total);
    }

    // ── getCalculatedCosts() ──────────────────────────────────────────────────

    public function testGetCalculatedCostsDividesMoneByDistance(): void
    {
        $car  = $this->makeCar();
        $trip = $this->makeCompletedTrip(0, 100, '2024-02-01', '2024-02-05');
        $car->addTrip($trip);
        $car->addExpense($this->makeExpense(50.0, '2024-03-01'));

        $cost = $car->getCalculatedCosts(new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->assertEquals(0.5, $cost);
    }

    public function testGetCalculatedCostsReturnsZeroWhenNoDistance(): void
    {
        $car = $this->makeCar();
        $car->addExpense($this->makeExpense(50.0, '2024-03-01'));

        $cost = $car->getCalculatedCosts(new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->assertSame(0.0, $cost);
    }
}
