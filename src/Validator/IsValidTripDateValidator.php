<?php

namespace App\Validator;

use App\Repository\TripRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsValidTripDateValidator extends ConstraintValidator
{
    public function __construct(private TripRepository $tripRep) {}

    public function validate($value, Constraint $constraint)
    {
        assert($constraint instanceof IsValidTripDate);

        if (null === $value || '' === $value) {
            return;
        }

        assert($value instanceof \DateTimeInterface);

        $lastTrip = $this->tripRep->findLast();

        if ($value < $lastTrip->getEndDate()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ tripDate }}', $value->format('Y-m-d H:i:s'))
                ->setParameter('{{ lastTripEndDate }}', $lastTrip->getEndDate()->format('Y-m-d H:i:s'))
                ->addViolation()
            ;
        }
    }
}
