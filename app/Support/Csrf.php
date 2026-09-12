<?php

declare(strict_types=1);

namespace Pharmacy\Support;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(string $token): void
    {
        $expected = (string) ($_SESSION['csrf_token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            throw new RuntimeException('Invalid CSRF token');
        }
    }
}
