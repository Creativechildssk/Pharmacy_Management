<?php

declare(strict_types=1);

namespace Pharmacy\Database;

use PDO;
use RuntimeException;

final class Connection
{
    public static function fromConfig(array $config): PDO
    {
        foreach (['host','port','database','username','password'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException('Database configuration is missing');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
