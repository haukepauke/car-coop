<?php

namespace App\Tests\Unit\Entity;

use App\Entity\CarHandbook;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class CarHandbookTest extends TestCase
{
    public function testContentLengthIsValidated(): void
    {
        $handbook = new CarHandbook();
        $handbook->setContent(str_repeat('x', CarHandbook::MAX_CONTENT_LENGTH + 1));

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($handbook);

        $this->assertGreaterThan(0, $violations->count());
    }
}
