<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = $config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            (int) $db['port'],
            $db['database'],
            $db['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO($dsn, (string) $db['username'], (string) $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Nu se poate conecta la baza de date.');
        }

        self::$pdo = $pdo;
        return self::$pdo;
    }
}

