<?php

declare(strict_types=1);

function fail(string $message, int $code = 1): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function usage(): void
{
    $msg = implode(PHP_EOL, [
        'Usage:',
        '  php scripts/update_local_php_config.php --config=PATH [options]',
        '',
        'Options:',
        '  --base-url=URL',
        '  --git-branch=BRANCH',
        '  --cors-origin=ORIGIN',
        '  --cors-allow-credentials=0|1',
        '  --token-secret=64HEX',
        '  --token-ttl-days=N',
        '  --print-secret',
        '  --dry-run',
    ]);
    fwrite(STDERR, $msg . PHP_EOL);
}

function cleanUrl(string $raw): string
{
    $s = trim($raw);
    $s = trim($s, " \t\n\r\0\x0B`\"'<>");
    return trim($s);
}

function clampInt(int $v, int $min, int $max): int
{
    if ($v < $min) {
        return $min;
    }
    if ($v > $max) {
        return $max;
    }
    return $v;
}

function exportPhp(array $value, int $indent = 0): string
{
    $pad = str_repeat('    ', $indent);
    $pad2 = str_repeat('    ', $indent + 1);
    $parts = [];
    $isList = array_keys($value) === range(0, count($value) - 1);
    foreach ($value as $k => $v) {
        $key = $isList ? '' : var_export($k, true) . ' => ';
        if (is_array($v)) {
            $parts[] = $pad2 . $key . exportPhp($v, $indent + 1) . ',';
            continue;
        }
        $parts[] = $pad2 . $key . var_export($v, true) . ',';
    }
    if (!$parts) {
        return '[]';
    }
    return "[\n" . implode("\n", $parts) . "\n" . $pad . "]";
}

$opts = getopt('', [
    'config:',
    'base-url::',
    'git-branch::',
    'cors-origin::',
    'cors-allow-credentials::',
    'token-secret::',
    'token-ttl-days::',
    'print-secret',
    'dry-run',
]);

$configPath = isset($opts['config']) ? (string) $opts['config'] : '';
if ($configPath === '') {
    usage();
    fail('Missing --config=PATH');
}

$local = [];
if (is_file($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) {
        $local = $loaded;
    } else {
        fail('Config file did not return an array: ' . $configPath);
    }
}

$baseUrl = cleanUrl(isset($opts['base-url']) ? (string) $opts['base-url'] : 'https://wsm.greensh3ll.com');
if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
    fail('Invalid --base-url');
}

$gitBranch = trim(isset($opts['git-branch']) ? (string) $opts['git-branch'] : 'main');
if ($gitBranch === '' || !preg_match('/^[A-Za-z0-9._\\/-]{1,120}$/', $gitBranch)) {
    fail('Invalid --git-branch');
}

$corsOrigin = cleanUrl(isset($opts['cors-origin']) ? (string) $opts['cors-origin'] : $baseUrl);
if ($corsOrigin === '' || filter_var($corsOrigin, FILTER_VALIDATE_URL) === false) {
    fail('Invalid --cors-origin');
}

$allowCredRaw = isset($opts['cors-allow-credentials']) ? (string) $opts['cors-allow-credentials'] : '0';
$allowCred = $allowCredRaw === '1';

$ttlDaysRaw = isset($opts['token-ttl-days']) ? (string) $opts['token-ttl-days'] : '30';
$ttlDays = clampInt((int) $ttlDaysRaw, 1, 365);

$tokenSecret = isset($opts['token-secret']) ? trim((string) $opts['token-secret']) : '';
if ($tokenSecret !== '') {
    if (!preg_match('/^[0-9a-f]{64}$/i', $tokenSecret)) {
        fail('Invalid --token-secret, expected 64 hex chars');
    }
} else {
    $existing = null;
    if (isset($local['security']) && is_array($local['security']) && isset($local['security']['api_token_secret'])) {
        $existing = is_string($local['security']['api_token_secret']) ? trim($local['security']['api_token_secret']) : '';
    }
    if (is_string($existing) && preg_match('/^[0-9a-f]{64}$/i', $existing)) {
        $tokenSecret = $existing;
    } else {
        $tokenSecret = bin2hex(random_bytes(32));
        if (isset($opts['print-secret'])) {
            fwrite(STDOUT, "Generated api_token_secret: " . $tokenSecret . PHP_EOL);
        }
    }
}

if (!isset($local['app']) || !is_array($local['app'])) {
    $local['app'] = [];
}
if (!isset($local['update']) || !is_array($local['update'])) {
    $local['update'] = [];
}
if (!isset($local['security']) || !is_array($local['security'])) {
    $local['security'] = [];
}
if (!isset($local['api']) || !is_array($local['api'])) {
    $local['api'] = [];
}

$local['app']['base_url'] = $baseUrl;
$local['update']['git_branch'] = $gitBranch;
$local['security']['api_token_secret'] = $tokenSecret;
$local['security']['api_token_ttl_days'] = $ttlDays;
$local['api']['cors_allowed_origins'] = [$corsOrigin];
$local['api']['cors_allow_credentials'] = $allowCred;

$out = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . exportPhp($local) . ";\n";

if (isset($opts['dry-run'])) {
    fwrite(STDOUT, $out);
    exit(0);
}

$dir = dirname($configPath);
if (!is_dir($dir)) {
    fail('Directory does not exist: ' . $dir);
}

if (file_put_contents($configPath, $out) === false) {
    fail('Failed to write: ' . $configPath);
}

fwrite(STDOUT, "Updated: " . $configPath . PHP_EOL);
