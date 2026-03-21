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
        $prop = new \ReflectionProperty($entity, 'id');
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

    private function makeCar(): Car
    {
        $car = new Car();
        $car->setName('Test Car');
        $car->setMileage(0);
        $car->setMilageUnit('km');
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

    // ── computePriceAdjustment – no adjustment needed ─────────────────────────

    public function testReturnsNullWhenActualCostIsZero(): void
    {
        $car = $this->makeCar();
        $ut  = $this->makeUserType($car, 0.30);
        $car->addUserType($ut);

        $result = $this->service->computePriceAdjustment($car, 0.0);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoActiveUserTypes(): void
    {
        $car = $this->makeCar();
        // inactive user type
        $ut = $this->makeUserType($car, 0.30, false);
        $car->addUserType($ut);

        $result = $this->service->computePriceAdjustment($car, 0.35);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPriceWithinFivePercent(): void
    {
        $car = $this->makeCar();
        $ut  = $this->makeUserType($car, 0.30);
        $car->addUserType($ut);

        // 0.30 * 1.04 = 0.312, within 5 % of 0.30
        $result = $this->service->computePriceAdjustment($car, 0.312);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPriceClearlyWithinFivePercent(): void
    {
        $car = $this->makeCar();
        $ut  = $this->makeUserType($car, 0.30);
        $car->addUserType($ut);

        // 4 % above current price → still within 5 % threshold
        $result = $this->service->computePriceAdjustment($car, 0.312);

        $this->assertNull($result);
    }

    // ── computePriceAdjustment – increase ────────────────────────────────────

    public function testSuggestsIncreaseWhenActualCostIsHigher(): void
    {
        $car = $this->makeCar();
        $ut  = $this->makeUserType($car, 0.30);
        $car->addUserType($ut);

        // 0.36 / 0.30 = 1.20 → 20 % increase
        $result = $this->service->computePriceAdjustment($car, 0.36);

        $this->assertNotNull($result);
        $this->assertSame('increase', $result['direction']);
        $this->assertEquals(20.0, $result['adjustmentPercent']);
    }

    public function testSuggestedPriceIsCeiledToTwoDecimals(): void
    {
        $car = $this->makeCar();
        $ut  = $this->makeUserType($car, 0.30);
        $car->addUserType($ut);

        // factor = 0.37 / 0.30 = 1.2333...
        // suggested = ceil(0.30 * 1.2333... * 100) / 100 = ceil(37.0) / 100 = 0.37
        $result = $this->service->computePriceAdjustment($car, 0.37);

        $this->assertNotNull($result);
        $entry = $result['userTypes'][0];
        $this->assertGreaterThanOrEqual($entry['current'], $entry['suggested']);
        // Must be exactly 2 decimal places
        $this->assertSame(round($entry['suggested'], 2), $entry['suggested']);
    }

    // ── computePriceAdjustment – decrease ────────────────────────────────────

    public function testSuggestsDecreaseWhenActualCostIsLower(): void
    {
        $car = $this->makeCar();
        $ut  = $this->makeUserType($car, 0.30);
        $car->addUserType($ut);

        // 0.24 / 0.30 = 0.80 → -20 %
        $result = $this->service->computePriceAdjustment($car, 0.24);

        $this->assertNotNull($result);
        $this->assertSame('decrease', $result['direction']);
        $this->assertEquals(-20.0, $result['adjustmentPercent']);
    }

    // ── computePriceAdjustment – occasional use groups ────────────────────────

    public function testOccasionalUseGroupExcludedFromAverageCalculation(): void
    {
        $car     = $this->makeCar();
        $regular = $this->makeUserType($car, 0.30, true, false);
        $regular->setName('Members');
        $guest   = $this->makeUserType($car, 0.10, true, true);  // very low price
        $guest->setName('Guests');
        $car->addUserType($regular);
        $car->addUserType($guest);

        // If guests were included, avg = (0.30 + 0.10) / 2 = 0.20
        // factor would be 0.36 / 0.20 = 1.80 → 80 %
        // With guests excluded, avg = 0.30, factor = 0.36 / 0.30 = 1.20 → 20 %
        $result = $this->service->computePriceAdjustment($car, 0.36);

        $this->assertNotNull($result);
        $this->assertEquals(20.0, $result['adjustmentPercent']);
    }

    public function testOccasionalUseGroupStillReceivesSuggestedPrice(): void
    {
        $car     = $this->makeCar();
        $regular = $this->makeUserType($car, 0.30, true, false);
        $regular->setName('Members');
        $guest   = $this->makeUserType($car, 0.10, true, true);
        $guest->setName('Guests');
        $car->addUserType($regular);
        $car->addUserType($guest);

        // factor = 0.36 / 0.30 = 1.20
        // guest suggested = ceil(0.10 * 1.20 * 100) / 100 = ceil(12) / 100 = 0.12
        $result = $this->service->computePriceAdjustment($car, 0.36);

        $this->assertNotNull($result);
        $this->assertCount(2, $result['userTypes']);

        $guestEntry   = array_values(array_filter($result['userTypes'], fn($e) => $e['userType']->isOccasionalUse()))[0];
        $regularEntry = array_values(array_filter($result['userTypes'], fn($e) => !$e['userType']->isOccasionalUse()))[0];

        $this->assertEquals(0.12, $guestEntry['suggested']);
        $this->assertEquals(0.36, $regularEntry['suggested']);
    }

    public function testFallsBackToAllTypesWhenAllAreOccasional(): void
    {
        $car   = $this->makeCar();
        $guest = $this->makeUserType($car, 0.20, true, true);
        $car->addUserType($guest);

        // No regular types → falls back to all types for avg
        $result = $this->service->computePriceAdjustment($car, 0.24);

        // 0.24 / 0.20 = 1.20 → 20 % increase (> 5 %)
        $this->assertNotNull($result);
        $this->assertSame('increase', $result['direction']);
    }

    // ── computePriceAdjustment – multiple user types ──────────────────────────

    public function testMultipleRegularTypesUseAveragePriceForFactor(): void
    {
        $car = $this->makeCar();
        $ut1 = $this->makeUserType($car, 0.20);
        $ut1->setName('Basic');
        $ut2 = $this->makeUserType($car, 0.40);
        $ut2->setName('Premium');
        $car->addUserType($ut1);
        $car->addUserType($ut2);

        // avg = (0.20 + 0.40) / 2 = 0.30; actual = 0.36 → factor = 1.20
        $result = $this->service->computePriceAdjustment($car, 0.36);

        $this->assertNotNull($result);
        $this->assertEquals(20.0, $result['adjustmentPercent']);

        $amounts = array_column($result['userTypes'], 'suggested');
        // Basic: ceil(0.20 * 1.20 * 100) / 100 = ceil(24) / 100 = 0.24
        // Premium: ceil(0.40 * 1.20 * 100) / 100 = ceil(48) / 100 = 0.48
        $this->assertContains(0.24, $amounts);
        $this->assertContains(0.48, $amounts);
    }

    public function testInactiveUserTypesIgnoredInPriceAdjustment(): void
    {
        $car    = $this->makeCar();
        $active = $this->makeUserType($car, 0.30, true);
        $inactive = $this->makeUserType($car, 5.00, false); // would massively skew avg if included
        $car->addUserType($active);
        $car->addUserType($inactive);

        $result = $this->service->computePriceAdjustment($car, 0.36);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['userTypes']);
        $this->assertEquals(20.0, $result['adjustmentPercent']);
    }
}
