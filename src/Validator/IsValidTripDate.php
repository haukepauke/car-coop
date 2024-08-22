<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class IsValidTripDate extends Constraint
{
    /*
     * Any public properties become valid options for the annotation.
     * Then, use these in your validator class.
     */
    public $message = 'The trip date "{{ tripDate }}" is before the last trips end date: {{ lastTripEndDate }}';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
