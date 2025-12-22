<?php

declare(strict_types=1);

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

$renderInternalError = static function (string $details) use ($debug): void {
    $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Eroare</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:20px}';
    echo 'pre{white-space:pre-wrap;background:#111;color:#eee;padding:12px;border-radius:8px;overflow:auto}</style></head><body>';
    echo '<h2>Eroare interna (500)</h2>';
    echo '<p>Pagina de instalare a intampinat o eroare pe server.</p>';
    if ($debug) {
        echo '<pre>' . $esc($details) . '</pre>';
    } else {
        echo '<p>Pentru detalii, incearca <code>?debug=1</code> temporar.</p>';
    }
    echo '</body></html>';
};

set_exception_handler(static function (Throwable $e) use ($renderInternalError): void {
    error_log('WSM install exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $renderInternalError($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    exit;
});

register_shutdown_function(static function () use ($renderInternalError): void {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $type = (int) ($err['type'] ?? 0);
    if (!in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $msg = (string) ($err['message'] ?? 'Eroare necunoscuta');
    $file = (string) ($err['file'] ?? '');
    $line = (int) ($err['line'] ?? 0);
    error_log('WSM install fatal: ' . $msg . ' in ' . $file . ':' . $line);
    $renderInternalError($msg . "\n" . $file . ':' . $line);
});

session_name('gsh3ll_wsm_install');
if (!session_start([
    'cookie_httponly' => true,
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax',
])) {
    throw new RuntimeException('Nu pot porni sesiunea.');
}

$configPath = __DIR__ . '/../config/local.php';

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrfVerify(?string $t): bool
{
    return is_string($t) && isset($_SESSION['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function passwordComplex(string $password, int $min = 12): bool
{
    if (strlen($password) < $min) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/\d/', $password)) {
        return false;
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return false;
    }
    return true;
}

$error = '';
$success = '';

if (is_file($configPath)) {
    $success = 'Aplicatia pare deja configurata. Poti merge la login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf'] ?? null)) {
        $error = 'Sesiune invalida. Reincarca pagina.';
    } else {
        $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $dbPort = (int) ($_POST['db_port'] ?? 3306);
        $dbName = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        $baseUrl = trim((string) ($_POST['base_url'] ?? ''));

        $username = trim((string) ($_POST['superadmin_username'] ?? 'superadmin'));
        $name = trim((string) ($_POST['superadmin_name'] ?? 'SuperAdmin'));
        $password = (string) ($_POST['superadmin_password'] ?? '');

        if ($dbName === '' || $dbUser === '' || $username === '' || $name === '') {
            $error = 'Campuri obligatorii lipsa.';
        } elseif (!passwordComplex($password, 12)) {
            $error = 'Parola trebuie sa fie complexa (minim 12, litere mari/mici, cifra, simbol).';
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
            try {
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (Throwable $e) {
                $pdo = null;
                $error = 'Nu se poate conecta la baza de date. Verifica datele.';
            }

            if (!$error && $pdo instanceof PDO) {
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (!is_file($schemaFile)) {
                    $error = 'Lipseste schema SQL.';
                } else {
                    $sql = (string) file_get_contents($schemaFile);
                    $statements = preg_split("/;\s*\n/", $sql);
                    $pdo->beginTransaction();
                    try {
                        foreach ($statements as $stmt) {
                            $stmt = trim($stmt);
                            if ($stmt === '' || strncmp($stmt, '--', 2) === 0) {
                                continue;
                            }
                            $pdo->exec($stmt);
                        }

                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $pdo->prepare(
                            "INSERT INTO users (name, username, password_hash, role, is_active, created_at, updated_at)
                             VALUES (?, ?, ?, 'SuperAdmin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role='SuperAdmin', is_active=1, updated_at=UTC_TIMESTAMP()"
                        )->execute([$name, $username, $hash]);

                        $csrfKey = bin2hex(random_bytes(32));
                        $local = [
                            'app' => [
                                'base_url' => $baseUrl,
                            ],
                            'db' => [
                                'host' => $dbHost,
                                'port' => $dbPort,
                                'database' => $dbName,
                                'username' => $dbUser,
                                'password' => $dbPass,
                            ],
                            'security' => [
                                'csrf_key' => $csrfKey,
                            ],
                        ];

                        $php = "<?php\n\nreturn " . var_export($local, true) . ";\n";
                        $dir = dirname($configPath);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        if (file_put_contents($configPath, $php) === false) {
                            throw new RuntimeException('Nu se poate scrie config/local.php');
                        }

                        $pdo->commit();
                        $success = 'Instalare reusita. Mergi la login.';
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = $e->getMessage() === 'Nu se poate scrie config/local.php'
                            ? 'Nu pot scrie fisierul config/local.php. Seteaza permisiuni (755/644).'
                            : 'Eroare la instalare. Verifica schema si permisiunile.';
                    }
                }
            }
        }
    }
}

?><!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install - GreenSh3ll WSM</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="container" style="max-width: 880px">
  <div class="card" style="margin-top: 22px">
    <h2 style="margin-top:0">Install - GreenSh3ll Wood Shop Manager</h2>
    <?php if ($error): ?>
      <div class="error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div style="color: var(--ok)"><?= h($success) ?></div>
      <div style="margin-top:12px">
        <a class="btn primary" href="/?r=auth/login">Login</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top: 12px">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <h3 style="margin-top:0">Baza de date</h3>
      <div class="grid">
        <div class="col-6">
          <label>DB host</label>
          <input name="db_host" required value="<?= h((string) ($_POST['db_host'] ?? 'localhost')) ?>">
        </div>
        <div class="col-6">
          <label>DB port</label>
          <input name="db_port" required value="<?= h((string) ($_POST['db_port'] ?? '3306')) ?>">
        </div>
        <div class="col-6">
          <label>DB name</label>
          <input name="db_name" required value="<?= h((string) ($_POST['db_name'] ?? '')) ?>">
        </div>
        <div class="col-6">
          <label>DB user</label>
          <input name="db_user" required value="<?= h((string) ($_POST['db_user'] ?? '')) ?>">
        </div>
        <div class="col-6">
          <label>DB password</label>
          <input name="db_pass" type="password" value="<?= h((string) ($_POST['db_pass'] ?? '')) ?>">
        </div>
        <div class="col-6">
          <label>Base URL (optional)</label>
          <input name="base_url" placeholder="https://domeniu.ro" value="<?= h((string) ($_POST['base_url'] ?? '')) ?>">
        </div>
      </div>

      <h3>SuperAdmin</h3>
      <div class="grid">
        <div class="col-6">
          <label>Nume</label>
          <input name="superadmin_name" required value="<?= h((string) ($_POST['superadmin_name'] ?? 'SuperAdmin')) ?>">
        </div>
        <div class="col-6">
          <label>Username</label>
          <input name="superadmin_username" required value="<?= h((string) ($_POST['superadmin_username'] ?? 'superadmin')) ?>">
        </div>
        <div class="col-12">
          <label>Parola (min 12 + litere mari/mici + cifra + simbol)</label>
          <input name="superadmin_password" type="password" required>
        </div>
        <div class="col-12 row" style="justify-content:flex-end">
          <button class="btn primary" type="submit">Instaleaza</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
