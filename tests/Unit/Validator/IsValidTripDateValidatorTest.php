<?php

namespace App\Tests\Unit\Validator;

use App\Entity\Car;
use App\Entity\Trip;
use App\Repository\TripRepository;
use App\Validator\IsValidTripDate;
use App\Validator\IsValidTripDateValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;

class IsValidTripDateValidatorTest extends TestCase
{
    private TripRepository&MockObject $repo;
    private ExecutionContext&MockObject $context;
    private IsValidTripDateValidator $validator;
    private IsValidTripDate $constraint;

    protected function setUp(): void
    {
        $this->repo       = $this->createMock(TripRepository::class);
        $this->context    = $this->createMock(ExecutionContext::class);
        $this->validator  = new IsValidTripDateValidator($this->repo);
        $this->validator->initialize($this->context);
        $this->constraint = new IsValidTripDate();
    }

    private function makeTrip(string $startDate, string $endDate, int $startMileage = 0, int $endMileage = 100): Trip
    {
        $car = new Car();
        $trip = new Trip();
        $trip->setCar($car);
        $trip->setStartMileage($startMileage);
        $trip->setEndMileage($endMileage);
        $trip->setStartDate(new \DateTime($startDate));
        $trip->setEndDate(new \DateTime($endDate));
        $trip->setType('vacation');
        return $trip;
    }

    private function expectNoViolations(): void
    {
        $this->context->expects($this->never())->method('buildViolation');
    }

    // ── no future dates ───────────────────────────────────────────────────────

    public function testValidTripWithPastDatesPassesValidation(): void
    {
        $this->repo->method('findPreviousByMileage')->willReturn(null);
        $this->expectNoViolations();

        $trip = $this->makeTrip('2024-01-01', '2024-01-05');
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripWithFutureStartDateFails(): void
    {
        $tomorrow = (new \DateTime('+1 day'))->format('Y-m-d');
        $today    = (new \DateTime('today'))->format('Y-m-d');
        $this->repo->method('findPreviousByMileage')->willReturn(null);

        $builder = $this->createMock(ConstraintViolationBuilder::class);
        $builder->method('atPath')->with('startDate')->willReturn($builder);
        $builder->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($this->constraint->messageFuture)
            ->willReturn($builder);

        $trip = $this->makeTrip($tomorrow, $today);
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripWithFutureEndDateFails(): void
    {
        $tomorrow = (new \DateTime('+1 day'))->format('Y-m-d');
        $this->repo->method('findPreviousByMileage')->willReturn(null);

        $builder = $this->createMock(ConstraintViolationBuilder::class);
        $builder->method('atPath')->willReturn($builder);
        $builder->method('setParameter')->willReturn($builder);
        $builder->expects($this->atLeastOnce())->method('addViolation');

        $this->context->expects($this->atLeastOnce())
            ->method('buildViolation')
            ->with($this->constraint->messageFuture)
            ->willReturn($builder);

        $trip = $this->makeTrip('2024-01-01', $tomorrow);
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripStartingTodayPassesValidation(): void
    {
        $today = (new \DateTime('today'))->format('Y-m-d');
        $this->repo->method('findPreviousByMileage')->willReturn(null);
        $this->expectNoViolations();

        $trip = $this->makeTrip($today, $today);
        $this->validator->validate($trip, $this->constraint);
    }

    // ── no trip before previous trip ended ───────────────────────────────────

    public function testTripStartingAfterPreviousTripEndPassesValidation(): void
    {
        $previous = $this->makeTrip('2024-01-01', '2024-01-10');
        $this->repo->method('findPreviousByMileage')->willReturn($previous);
        $this->expectNoViolations();

        $trip = $this->makeTrip('2024-01-11', '2024-01-15', 100, 200);
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripStartingOnSameDayAsPreviousTripEndPassesValidation(): void
    {
        $previous = $this->makeTrip('2024-01-01', '2024-01-10');
        $this->repo->method('findPreviousByMileage')->willReturn($previous);
        $this->expectNoViolations();

        $trip = $this->makeTrip('2024-01-10', '2024-01-15', 100, 200);
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripStartingBeforePreviousTripEndFails(): void
    {
        $previous = $this->makeTrip('2024-01-01', '2024-01-10');
        $this->repo->method('findPreviousByMileage')->willReturn($previous);

        $builder = $this->createMock(ConstraintViolationBuilder::class);
        $builder->method('atPath')->with('startDate')->willReturn($builder);
        $builder->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($this->constraint->messageBeforePrevious)
            ->willReturn($builder);

        $trip = $this->makeTrip('2024-01-05', '2024-01-15', 100, 200);
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripStartingBeforePreviousTripEndWhenMileageContinuousFails(): void
    {
        // Previous trip ends at mileage 1000; new trip starts at 1000 (continuous mileage).
        // This is the typical case — the repo must return the previous trip via <=.
        $previous = $this->makeTrip('2024-01-01', '2024-01-10', 0, 1000);
        $this->repo->method('findPreviousByMileage')->willReturn($previous);

        $builder = $this->createMock(ConstraintViolationBuilder::class);
        $builder->method('atPath')->with('startDate')->willReturn($builder);
        $builder->method('setParameter')->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($this->constraint->messageBeforePrevious)
            ->willReturn($builder);

        // New trip starts at mileage 1000 but with a date before the previous trip ended
        $trip = $this->makeTrip('2024-01-05', '2024-01-15', 1000, 1200);
        $this->validator->validate($trip, $this->constraint);
    }

    public function testTripWithNoPreviousTripPassesValidation(): void
    {
        $this->repo->method('findPreviousByMileage')->willReturn(null);
        $this->expectNoViolations();

        $trip = $this->makeTrip('2024-01-01', '2024-01-05');
        $this->validator->validate($trip, $this->constraint);
    }
}
