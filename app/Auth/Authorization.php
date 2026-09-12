<?php

declare(strict_types=1);

namespace Pharmacy\Auth;

final class Authorization
{
    public static function hasRole(string ...$roleCodes): bool
    {
        $roles = $_SESSION['user']['roles'] ?? [];
        return count(array_intersect($roleCodes, $roles)) > 0;
    }

    public static function requireLogin(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireRole(string ...$roleCodes): void
    {
        self::requireLogin();
        if (!self::hasRole(...$roleCodes)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}
