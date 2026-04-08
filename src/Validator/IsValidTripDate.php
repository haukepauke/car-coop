<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class IsValidTripDate extends Constraint
{
    public string $messageFuture        = 'trip.date.future';
    public string $messageBeforePrevious = 'trip.date.before_previous';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
