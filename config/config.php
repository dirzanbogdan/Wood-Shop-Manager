<?php

declare(strict_types=1);

$base = [
    'app' => [
        'name' => 'GreenSh3ll Wood Shop Manager',
        'base_url' => '',
        'session_name' => 'gsh3ll_wsm',
        'version_major' => 2,
        'version_minor' => 4,
        'version_date' => '22122025',
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
        'api_token_secret' => '',
        'api_token_ttl_days' => 30,
    ],
    'api' => [
        'cors_allowed_origins' => ['*'],
        'cors_allow_credentials' => false,
    ],
    'mobile' => [
        'apk_path' => '/downloads/wsm.apk',
        'latest_version' => '1.0.0',
        'latest_build' => 1,
    ],
    'update' => [
        'git_branch' => 'main',
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
