<?php

declare(strict_types=1);

$base = [
    'app' => [
        'name' => 'GreenSh3ll Wood Shop Manager',
        'base_url' => '',
        'session_name' => 'gsh3ll_wsm',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'csrf_key' => 'csrf_token',
        'password_min_length' => 12,
    ],
];

$localPath = __DIR__ . '/local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $base = array_replace_recursive($base, $local);
    }
}

return $base;

