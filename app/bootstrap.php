<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

date_default_timezone_set('UTC');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;
    if (is_file($path)) {
        require $path;
    }
});

$config = require __DIR__ . '/../config/config.php';

$major = (int) ($config['app']['version_major'] ?? 0);
$minor = (int) ($config['app']['version_minor'] ?? 0);
$date = gmdate('dmY');
if ($major > 0 && $minor > 0) {
    $config['app']['version'] = 'v' . $major . '.' . $date . '.' . str_pad((string) $minor, 3, '0', STR_PAD_LEFT);
} else {
    $config['app']['version'] = '';
}

session_name($config['app']['session_name']);
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax',
]);

return $config;
