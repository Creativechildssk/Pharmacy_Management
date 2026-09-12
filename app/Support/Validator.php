<?php

declare(strict_types=1);

namespace Pharmacy\Support;

use InvalidArgumentException;

final class Validator
{
    public static function positiveInt(mixed $value): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false || $filtered <= 0) {
            throw new InvalidArgumentException('Value must be a positive integer');
        }

        return $filtered;
    }

    public static function requiredString(mixed $value, int $maxLength = 255): string
    {
        $value = trim((string) $value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($value === '' || $length > $maxLength) {
            throw new InvalidArgumentException('Required text is invalid');
        }

        return $value;
    }

    public static function positiveFloat(mixed $value): float
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            throw new InvalidArgumentException('Value must be greater than zero');
        }

        return (float) $value;
    }
}
