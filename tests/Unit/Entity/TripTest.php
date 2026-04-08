<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Trip;
use PHPUnit\Framework\TestCase;

class TripTest extends TestCase
{
    private function makeTrip(int $start, int $end, string $startDate, string $endDate): Trip
    {
        $trip = new Trip();
        $trip->setStartMileage($start);
        $trip->setEndMileage($end);
        $trip->setStartDate(new \DateTime($startDate));
        $trip->setEndDate(new \DateTime($endDate));
        $trip->setType('vacation');
        return $trip;
    }

    // ── getMileage() ──────────────────────────────────────────────────────────

    public function testGetMileageReturnsDistanceForCompletedTrip(): void
    {
        $trip = $this->makeTrip(10000, 10350, '2024-01-01', '2024-01-05');
        $this->assertSame(350, $trip->getMileage());
    }

    // ── getCosts() ────────────────────────────────────────────────────────────

    public function testGetCostsReturnsValueForCompletedTrip(): void
    {
        $trip = $this->makeTrip(1000, 1200, '2024-01-01', '2024-01-05');
        $trip->setCosts(49.90);
        $this->assertSame(49.90, $trip->getCosts());
    }

    // ── getComment() ──────────────────────────────────────────────────────────

    public function testGetCommentReturnsEmptyStringWhenNotSet(): void
    {
        $trip = new Trip();
        $this->assertSame('', $trip->getComment());
    }

    public function testGetCommentReturnsSetValue(): void
    {
        $trip = new Trip();
        $trip->setComment('Great road trip!');
        $this->assertSame('Great road trip!', $trip->getComment());
    }
}
