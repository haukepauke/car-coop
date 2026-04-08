<?php

namespace App\Validator;

use App\Entity\Trip;
use App\Repository\TripRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class IsValidTripDateValidator extends ConstraintValidator
{
    public function __construct(private TripRepository $tripRep) {}

    public function validate($trip, Constraint $constraint): void
    {
        assert($constraint instanceof IsValidTripDate);

        if (!$trip instanceof Trip) {
            throw new UnexpectedValueException($trip, Trip::class);
        }

        $today     = new \DateTimeImmutable('today');
        $startDate = $trip->getStartDate();
        $endDate   = $trip->getEndDate();

        if ($startDate instanceof \DateTimeInterface && $startDate > $today) {
            $this->context->buildViolation($constraint->messageFuture)
                ->atPath('startDate')
                ->addViolation();
        }

        if ($endDate instanceof \DateTimeInterface && $endDate > $today) {
            $this->context->buildViolation($constraint->messageFuture)
                ->atPath('endDate')
                ->addViolation();
        }

        if ($startDate instanceof \DateTimeInterface && $trip->getCar() !== null) {
            $previousTrip = $this->tripRep->findPreviousByMileage($trip);

            if ($previousTrip !== null && $previousTrip->getEndDate() instanceof \DateTimeInterface) {
                // Same day is allowed: start date must not be strictly before previous trip's end date
                $prevEnd = \DateTimeImmutable::createFromInterface($previousTrip->getEndDate())->setTime(0, 0);
                $start   = \DateTimeImmutable::createFromInterface($startDate)->setTime(0, 0);

                if ($start < $prevEnd) {
                    $this->context->buildViolation($constraint->messageBeforePrevious)
                        ->setParameter('{{ prevTripEndDate }}', $previousTrip->getEndDate()->format('d.m.Y'))
                        ->atPath('startDate')
                        ->addViolation();
                }
            }
        }
    }
}
