<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Router;

$config = require __DIR__ . '/../app/bootstrap.php';

$dbName = trim((string) ($config['db']['database'] ?? ''));
$dbUser = trim((string) ($config['db']['username'] ?? ''));
$localPath = __DIR__ . '/../config/local.php';
if ($dbName === '' || $dbUser === '' || !is_file($localPath)) {
    header('Location: /install.php');
    exit;
}

try {
    $pdo = DB::pdo($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Aplicatia nu este configurata sau nu se poate conecta la baza de date. Deschide /install.php.';
    exit;
}

Router::dispatch($config, $pdo);
