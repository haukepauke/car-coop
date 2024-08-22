<?php

namespace App\Validator;

use App\Entity\Trip;
use App\Repository\TripRepository;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


class IsValidTripDateValidator extends ConstraintValidator
{
    public function __construct(private TripRepository $tripRep) {}

    public function validate($trip, Constraint $constraint)
    {
        assert($constraint instanceof IsValidTripDate);

        if(!$trip instanceof Trip){
            throw new UnexpectedValueException($trip, Trip::class);
        }

        assert($trip->getStartDate() instanceof \DateTimeInterface);
        $startDate = $trip->getStartDate();

        $lastTrip = $this->tripRep->findLast($trip->getCar());

        if($lastTrip === null){
            return;
        }
        if ($startDate < $lastTrip->getEndDate()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ tripDate }}', $startDate->format('Y-m-d H:i:s'))
                ->setParameter('{{ lastTripEndDate }}', $lastTrip->getEndDate()->format('Y-m-d H:i:s'))
                ->addViolation()
            ;
        }
    }
}
