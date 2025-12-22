<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function user(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    public static function check(): bool
    {
        $u = self::user();
        return is_array($u) && isset($u['id']);
    }

    public static function requireRole(array $roles): void
    {
        $u = self::user();
        $role = is_array($u) && isset($u['role']) ? (string) $u['role'] : '';
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            echo 'Acces interzis.';
            exit;
        }
    }

    public static function attempt(PDO $pdo, string $username, string $password): bool
    {
        $stmt = $pdo->prepare('SELECT id, name, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['is_active'] !== 1) {
            return false;
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'username' => (string) $row['username'],
            'role' => (string) $row['role'],
        ];

        $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $row['id']]);
        session_regenerate_id(true);
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }
}

