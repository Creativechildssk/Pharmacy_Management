<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;
use Pharmacy\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_positive_int_accepts_positive_integer(): void
    {
        self::assertSame(7, Validator::positiveInt('7'));
    }

    public function test_positive_int_rejects_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::positiveInt(0);
    }

    public function test_required_string_trims_value(): void
    {
        self::assertSame('Paracetamol', Validator::requiredString('  Paracetamol  ', 50));
    }

    public function test_required_string_rejects_blank_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::requiredString('   ');
    }
}
