<?php

declare(strict_types=1);

namespace App\Core;

final class CSRF
{
    public static function token(string $key): string
    {
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[$key];
    }

    public static function verify(string $key, ?string $token): bool
    {
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($_SESSION[$key], $token);
    }
}

