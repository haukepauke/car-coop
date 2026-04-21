<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Entity\User;
use App\Entity\UserType;
use App\Service\CarReviewService;
use PHPUnit\Framework\TestCase;

class CarReviewServiceTest extends TestCase
{
    private CarReviewService $service;

    protected function setUp(): void
    {
        $this->service = new CarReviewService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setId(object $entity, int $id): void
    {
        $prop = new \ReflectionProperty(User::class, 'id');
        $prop->setValue($entity, $id);
    }

    private function makeUser(int $id, string $name = ''): User
    {
        $user = new User();
        $user->setEmail("user{$id}@test.com");
        $user->setName($name ?: "User {$id}");
        $user->setLocale('en');
        $user->setPassword('hashed');
        $this->setId($user, $id);
        return $user;
    }

    private function makeMeasuredUser(int $id, Car $car, float $balance, int $mileage, string $name = ''): User
    {
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['getBalance', 'getTripMileage', 'isActive'])
            ->getMock();

        $user->setEmail("user{$id}@test.com");
        $user->setName($name ?: "User {$id}");
        $user->setLocale('en');
        $user->setPassword('hashed');
        $this->setId($user, $id);

        $user->method('getBalance')
            ->willReturnCallback(fn(Car $requestedCar): float => $requestedCar === $car ? $balance : 0.0);
        $user->method('getTripMileage')
            ->willReturnCallback(fn(...$args): int => ($args[0] ?? null) === $car ? $mileage : 0);
        $user->method('isActive')
            ->willReturn(true);

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

    private function makeCarWithActualCost(float $actualCostPerUnit): Car
    {
        /** @var Car&\PHPUnit\Framework\MockObject\MockObject $car */
        $car = $this->getMockBuilder(Car::class)
            ->onlyMethods(['getCalculatedCosts'])
            ->getMock();

        $car->setName('Test Car');
        $car->setMileage(0);
        $car->setMilageUnit('km');
        $car->method('getCalculatedCosts')->willReturn($actualCostPerUnit);

        return $car;
    }

    private function makeUserType(Car $car, float $price = 0.30, bool $active = true, bool $occasionalUse = false): UserType
    {
        $ut = new UserType();
        $ut->setName('Members');
        $ut->setPricePerUnit($price);
        $ut->setCar($car);
        $ut->setActive($active);
        $ut->setAdmin(false);
        $ut->setOccasionalUse($occasionalUse);
        return $ut;
    }

    /**
     * Build a userBalances array as computePaymentProposals() expects it.
     *
     * @param array<int, float> $balances  id => balance
     */
    private function makeBalances(array $balances): array
    {
        $result = [];
        foreach ($balances as $id => $balance) {
            $result[] = ['user' => $this->makeUser($id), 'balance' => (float) $balance];
        }
        return $result;
    }

    // ── computePaymentProposals – edge cases ──────────────────────────────────

    public function testSingleUserProducesNoProposals(): void
    {
        $balances = $this->makeBalances([1 => 100.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertSame([], $proposals);
    }

    public function testEmptyBalancesProducesNoProposals(): void
    {
        $proposals = $this->service->computePaymentProposals([]);

        $this->assertSame([], $proposals);
    }

    public function testAllEqualBalancesProducesNoProposals(): void
    {
        $balances = $this->makeBalances([1 => 50.0, 2 => 50.0, 3 => 50.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertSame([], $proposals);
    }

    public function testNegligibleInequalityProducesNoProposals(): void
    {
        // Difference is < 5 % of sum-of-absolute-balances → suppressed
        $balances = $this->makeBalances([1 => 100.0, 2 => 99.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertSame([], $proposals);
    }

    // ── computePaymentProposals – two users ───────────────────────────────────

    public function testTwoUsersWithOppositeSignBalancesProposeSinglePayment(): void
    {
        // User 1 has +60 (creditor, receives), user 2 has -60 (debtor, pays) → avg = 0
        $balances = $this->makeBalances([1 => 60.0, 2 => -60.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertCount(1, $proposals);
        $this->assertSame(2, $proposals[0]['from']->getId()); // debtor pays
        $this->assertSame(1, $proposals[0]['to']->getId());   // creditor receives
        $this->assertEquals(60.0, $proposals[0]['amount']);
    }

    public function testTwoUsersWithSameSignPositiveBalancesEqualize(): void
    {
        // Both positive: avg = 75 → user 1 (balance 50, dev -25) pays user 2 (balance 100, dev +25)
        $balances = $this->makeBalances([1 => 50.0, 2 => 100.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertCount(1, $proposals);
        $this->assertEquals(25.0, $proposals[0]['amount']);
    }

    public function testTwoUsersWithSameSignNegativeBalancesEqualize(): void
    {
        // Both negative: avg = -75 → user 2 (balance -100, dev -25) pays user 1 (balance -50, dev +25)
        $balances = $this->makeBalances([1 => -50.0, 2 => -100.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertCount(1, $proposals);
        $this->assertEquals(25.0, $proposals[0]['amount']);
    }

    // ── computePaymentProposals – three users ─────────────────────────────────

    public function testThreeUsersWithDifferentBalancesProduceMinimalProposals(): void
    {
        // balances: 120, 60, -30  → avg = 50
        // deviations: +70, +10, -80
        // user 3 (dev -80) pays user 1 (dev +70) €70, remainder €10 pays user 2 (dev +10)
        $balances = $this->makeBalances([1 => 120.0, 2 => 60.0, 3 => -30.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertCount(2, $proposals);
        $totalTransferred = array_sum(array_column($proposals, 'amount'));
        $this->assertEquals(80.0, $totalTransferred);
        foreach ($proposals as $p) {
            $this->assertSame(3, $p['from']->getId());
        }
    }

    public function testThreeUsersNeedTwoPayments(): void
    {
        // A: 0, B: 60, C: -60 → avg = 0; B dev +60, C dev -60 → 1 payment
        $balances = $this->makeBalances([1 => 0.0, 2 => 60.0, 3 => -60.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        // User 3 pays user 2; user 1 is at avg
        $this->assertCount(1, $proposals);
        $this->assertEquals(60.0, $proposals[0]['amount']);
    }

    public function testFourUsersWithVeryDifferentBalances(): void
    {
        // balances: 200, 100, -50, -250  → avg = 0
        // deviations: +200, +100, -50, -250
        $balances = $this->makeBalances([1 => 200.0, 2 => 100.0, 3 => -50.0, 4 => -250.0]);

        $proposals = $this->service->computePaymentProposals($balances);

        $this->assertNotEmpty($proposals);

        // All 'from' users must have had negative deviation (below avg)
        $fromIds = array_map(fn($p) => $p['from']->getId(), $proposals);
        foreach ($fromIds as $id) {
            $this->assertContains($id, [3, 4]);
        }

        // All 'to' users must have had positive deviation (above avg)
        $toIds = array_map(fn($p) => $p['to']->getId(), $proposals);
        foreach ($toIds as $id) {
            $this->assertContains($id, [1, 2]);
        }

        // Total transferred must equal total positive deviation (200 + 100 = 300)
        $this->assertEquals(300.0, round(array_sum(array_column($proposals, 'amount')), 2));
    }

    public function testProposalsResultInEqualizedBalances(): void
    {
        $balances = $this->makeBalances([1 => 180.0, 2 => 60.0, 3 => -30.0, 4 => -90.0]);
        $avg = (180 + 60 - 30 - 90) / 4; // = 30

        $proposals = $this->service->computePaymentProposals($balances);

        // Apply proposals to a mutable copy of balances
        $adjusted = [1 => 180.0, 2 => 60.0, 3 => -30.0, 4 => -90.0];
        foreach ($proposals as $p) {
            $adjusted[$p['from']->getId()] += $p['amount'];
            $adjusted[$p['to']->getId()]   -= $p['amount'];
        }

        foreach ($adjusted as $balance) {
            $this->assertEqualsWithDelta($avg, $balance, 0.02);
        }
    }

    // ── buildReviewData / computePriceAdjustment ─────────────────────────────

    public function testBuildReviewDataOnlyProposesPaymentsBetweenRegularUsers(): void
    {
        $car = $this->makeCar();
        $regular = $this->makeUserType($car, 0.30, true, false);
        $occasional = $this->makeUserType($car, 0.55, true, true);
        $car->addUserType($regular);
        $car->addUserType($occasional);

        $userA = $this->makeMeasuredUser(1, $car, 60.0, 100, 'Alice');
        $userB = $this->makeMeasuredUser(2, $car, -60.0, 100, 'Bob');
        $guest = $this->makeMeasuredUser(3, $car, 100.0, 0, 'Guest');
        $regular->addUser($userA);
        $regular->addUser($userB);
        $occasional->addUser($guest);

        $review = $this->service->buildReviewData($car);

        $this->assertCount(3, $review['userBalances']);
        $this->assertCount(1, $review['paymentProposals']);
        $this->assertSame(2, $review['paymentProposals'][0]['from']->getId());
        $this->assertSame(1, $review['paymentProposals'][0]['to']->getId());
    }

    public function testReturnsNullWhenNoRegularPricedUserTypesExist(): void
    {
        $car = $this->makeCar();
        $guest = $this->makeUserType($car, 0.20, true, true);
        $guest->addUser($this->makeMeasuredUser(1, $car, 10.0, 100));
        $car->addUserType($guest);

        $this->assertNull($this->service->computePriceAdjustment($car));
    }

    public function testReturnsNullWhenCurrentRegularPricesAreAlreadyCloseToNeeded(): void
    {
        $car = $this->makeCarWithActualCost(0.30);
        $regular = $this->makeUserType($car, 0.30, true, false);
        $car->addUserType($regular);

        $regular->addUser($this->makeMeasuredUser(1, $car, 0.5, 100));
        $regular->addUser($this->makeMeasuredUser(2, $car, -0.5, 300));

        $this->assertNull($this->service->computePriceAdjustment($car));
    }

    public function testSuggestsIncreaseFromRegularUserBalancesAndMileage(): void
    {
        $car = $this->makeCarWithActualCost(0.34);
        $regularA = $this->makeUserType($car, 0.30, true, false);
        $regularA->setName('Crew');
        $regularB = $this->makeUserType($car, 0.40, true, false);
        $regularB->setName('Supporters');
        $occasional = $this->makeUserType($car, 0.55, true, true);
        $occasional->setName('Guests');
        $car->addUserType($regularA);
        $car->addUserType($regularB);
        $car->addUserType($occasional);

        $regularA->addUser($this->makeMeasuredUser(1, $car, 20.0, 100));
        $regularA->addUser($this->makeMeasuredUser(2, $car, 10.0, 200));
        $regularB->addUser($this->makeMeasuredUser(3, $car, 0.0, 300));
        $occasional->addUser($this->makeMeasuredUser(4, $car, 999.0, 9999));

        $result = $this->service->computePriceAdjustment($car);

        $this->assertNotNull($result);
        $this->assertSame('increase', $result['direction']);
        $this->assertSame('Crew', $result['crewUserType']->getName());
        $this->assertEquals(30.0, $result['adjustmentPercent']);
        $this->assertSame(30.0, $result['regularBalanceTotal']);
        $this->assertSame(0.39, $result['crewSuggested']);
        $this->assertSame([0.39, 0.49, 0.64], array_map(fn($entry) => $entry['suggested'], $result['userTypes']));
    }

    public function testSuggestsDecreaseAndKeepsOccasionalGapConstant(): void
    {
        $car = $this->makeCarWithActualCost(0.34);
        $regularA = $this->makeUserType($car, 0.36, true, false);
        $regularA->setName('Crew');
        $regularB = $this->makeUserType($car, 0.46, true, false);
        $occasional = $this->makeUserType($car, 0.61, true, true);
        $car->addUserType($regularA);
        $car->addUserType($regularB);
        $car->addUserType($occasional);

        $regularA->addUser($this->makeMeasuredUser(1, $car, -10.0, 100));
        $regularA->addUser($this->makeMeasuredUser(2, $car, -5.0, 200));
        $regularB->addUser($this->makeMeasuredUser(3, $car, 0.0, 300));

        $result = $this->service->computePriceAdjustment($car);

        $this->assertNotNull($result);
        $this->assertSame('decrease', $result['direction']);
        $this->assertSame('Crew', $result['crewUserType']->getName());
        $this->assertEquals(-12.5, $result['adjustmentPercent']);
        $this->assertSame(-15.0, $result['regularBalanceTotal']);
        $this->assertSame(0.31, $result['crewSuggested']);

        $suggestedByCurrentPrice = [];
        foreach ($result['userTypes'] as $entry) {
            $suggestedByCurrentPrice[(string) $entry['current']] = $entry['suggested'];
        }

        $this->assertSame(0.31, $suggestedByCurrentPrice['0.36']);
        $this->assertSame(0.41, $suggestedByCurrentPrice['0.46']);
        $this->assertSame(0.56, $suggestedByCurrentPrice['0.61']);
        $this->assertEqualsWithDelta(
            0.25,
            $suggestedByCurrentPrice['0.61'] - $suggestedByCurrentPrice['0.36'],
            0.001
        );
    }

    public function testAcceptedPriceDoesNotImmediatelyTriggerAnotherProposal(): void
    {
        $car = $this->makeCarWithActualCost(0.34);
        $regularA = $this->makeUserType($car, 0.39, true, false);
        $regularA->setName('Crew');
        $regularB = $this->makeUserType($car, 0.49, true, false);
        $car->addUserType($regularA);
        $car->addUserType($regularB);

        $regularA->addUser($this->makeMeasuredUser(1, $car, 20.0, 100));
        $regularA->addUser($this->makeMeasuredUser(2, $car, 10.0, 200));
        $regularB->addUser($this->makeMeasuredUser(3, $car, 0.0, 300));

        $this->assertNull($this->service->computePriceAdjustment($car));
    }

}
