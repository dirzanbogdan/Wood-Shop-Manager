<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Validator;

final class ApiController extends Controller
{
    private ?int $bearerUserId = null;
    private bool $bearerChecked = false;

    private function isGitLfsPointerFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = (string) @fread($fh, 256);
        fclose($fh);
        if ($head === '') {
            return false;
        }
        return str_contains($head, 'git-lfs.github.com/spec/v1');
    }

    private function baseMeta(array $meta = []): array
    {
        $version = isset($this->config['app']['version']) ? (string) $this->config['app']['version'] : '';
        return array_merge([
            'version' => $version,
            'utc' => gmdate('c'),
        ], $meta);
    }

    private function corsHeaders(): void
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
        if ($origin === '') {
            return;
        }

        $allowed = $this->config['api']['cors_allowed_origins'] ?? [];
        if (!is_array($allowed) || !$allowed) {
            return;
        }

        $allowed = array_values(array_filter(array_map('strval', $allowed), static fn ($v) => trim($v) !== ''));
        if (!$allowed) {
            return;
        }

        $allowAll = in_array('*', $allowed, true);
        if (!$allowAll && !in_array($origin, $allowed, true)) {
            return;
        }

        header('Access-Control-Allow-Origin: ' . ($allowAll ? '*' : $origin));
        if (!$allowAll) {
            header('Vary: Origin');
        }

        $allowCred = $this->config['api']['cors_allow_credentials'] ?? true;
        if ($allowAll) {
            $allowCred = false;
        }
        if ($allowCred === true) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Authorization, X-Access-Token');
        header('Access-Control-Max-Age: 600');
    }

    private function preflight(): void
    {
        $this->corsHeaders();
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            header('Content-Length: 0');
            exit;
        }
    }

    private function sendJson(array $payload, int $status = 200): void
    {
        $this->corsHeaders();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function ok($data = null, array $meta = [], int $status = 200): void
    {
        $this->sendJson([
            'ok' => true,
            'data' => $data,
            'error' => null,
            'meta' => $this->baseMeta($meta),
        ], $status);
    }

    private function okExit($data = null, array $meta = [], int $status = 200): void
    {
        $this->ok($data, $meta, $status);
        exit;
    }

    private function failExit(string $message, int $status = 400, string $code = 'error', ?array $fields = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if (is_array($fields)) {
            $error['fields'] = $fields;
        }

        $this->sendJson([
            'ok' => false,
            'data' => null,
            'error' => $error,
            'meta' => $this->baseMeta(),
        ], $status);
        exit;
    }

    private function inputJson(): array
    {
        $raw = (string) file_get_contents('php://input');
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getAuthorizationHeader(): string
    {
        $candidates = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
            'HTTP_X_AUTHORIZATION',
            'REDIRECT_HTTP_X_AUTHORIZATION',
            'HTTP_X_ACCESS_TOKEN',
            'REDIRECT_HTTP_X_ACCESS_TOKEN',
        ];
        foreach ($candidates as $k) {
            if (isset($_SERVER[$k]) && is_string($_SERVER[$k]) && trim($_SERVER[$k]) !== '') {
                return trim((string) $_SERVER[$k]);
            }
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $normalized = [];
                foreach ($headers as $k => $v) {
                    if (!is_string($k)) {
                        continue;
                    }
                    $normalized[strtolower($k)] = is_string($v) ? trim($v) : trim((string) $v);
                }
                $keys = ['authorization', 'x-authorization', 'x-access-token'];
                foreach ($keys as $k) {
                    if (isset($normalized[$k]) && $normalized[$k] !== '') {
                        return $normalized[$k];
                    }
                }
            }
        }
        return '';
    }

    private function bearerToken(): string
    {
        $h = $this->getAuthorizationHeader();
        if ($h === '') {
            return '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $h, $m)) {
            return trim((string) $m[1]);
        }
        return trim($h);
    }

    private function tokenSecret(): string
    {
        $s = (string) ($this->config['security']['api_token_secret'] ?? '');
        if (trim($s) !== '') {
            return $s;
        }
        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        return preg_match('/^[0-9a-f]{64}$/i', $csrfKey) ? $csrfKey : '';
    }

    private function tokenTtlSeconds(): int
    {
        $days = (int) ($this->config['security']['api_token_ttl_days'] ?? 30);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }
        return $days * 86400;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $b64url): string
    {
        $b64 = strtr($b64url, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        return is_string($decoded) ? $decoded : '';
    }

    private function issueBearerToken(int $userId): array
    {
        $secret = $this->tokenSecret();
        if (trim($secret) === '') {
            return ['ok' => false, 'message' => 'Token secret missing'];
        }

        $exp = time() + $this->tokenTtlSeconds();
        $payload = json_encode([
            'uid' => $userId,
            'exp' => $exp,
            'rnd' => bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload) || $payload === '') {
            return ['ok' => false, 'message' => 'Cannot create token'];
        }

        $payloadB64 = $this->base64UrlEncode($payload);
        $sig = hash_hmac('sha256', $payloadB64, $secret, true);
        $sigB64 = $this->base64UrlEncode($sig);
        return [
            'ok' => true,
            'token' => $payloadB64 . '.' . $sigB64,
            'expires_at' => gmdate('c', $exp),
        ];
    }

    private function bearerUserId(): ?int
    {
        if ($this->bearerChecked) {
            return $this->bearerUserId;
        }
        $this->bearerChecked = true;
        $this->bearerUserId = null;

        $token = $this->bearerToken();
        if ($token === '') {
            return null;
        }

        $secret = $this->tokenSecret();
        if (trim($secret) === '') {
            return null;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadB64, $sigB64] = $parts;
        if ($payloadB64 === '' || $sigB64 === '') {
            return null;
        }

        $sig = $this->base64UrlDecode($sigB64);
        if ($sig === '') {
            return null;
        }
        $expected = hash_hmac('sha256', $payloadB64, $secret, true);
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payloadRaw = $this->base64UrlDecode($payloadB64);
        if ($payloadRaw === '') {
            return null;
        }
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            return null;
        }
        $uid = isset($payload['uid']) ? (int) $payload['uid'] : 0;
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if ($uid < 1 || $exp < 1 || time() > $exp) {
            return null;
        }

        $this->bearerUserId = $uid;
        return $uid;
    }

    private function setSessionUserFromId(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, name, username, role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['is_active'] !== 1) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'username' => (string) $row['username'],
            'role' => (string) $row['role'],
        ];
        return true;
    }

    private function requireApiAuth(): array
    {
        if (!Auth::check()) {
            $uid = $this->bearerUserId();
            if ($uid !== null && $this->setSessionUserFromId($uid)) {
                $u = Auth::user();
                return is_array($u) ? $u : [];
            }
            $this->failExit('Unauthorized', 401, 'unauthorized');
        }
        $u = Auth::user();
        return is_array($u) ? $u : [];
    }

    private function requireApiRole(array $roles): void
    {
        $u = Auth::user();
        $role = is_array($u) && isset($u['role']) ? (string) $u['role'] : '';
        if (!in_array($role, $roles, true)) {
            $this->failExit('Forbidden', 403, 'forbidden');
        }
    }

    private function requireApiCsrf(array $input = []): void
    {
        if ($this->bearerUserId() !== null) {
            return;
        }

        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        if ($csrfKey === '') {
            return;
        }

        $header = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']) : '';
        $token = $header !== '' ? $header : (isset($input[$csrfKey]) ? (string) $input[$csrfKey] : '');
        if (!CSRF::verify($csrfKey, $token !== '' ? $token : null)) {
            $this->failExit('Invalid CSRF', 400, 'invalid_csrf');
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) (($stmt->fetch()['c'] ?? 0)) > 0;
    }

    public function v1Ping(): void
    {
        $this->preflight();
        $u = Auth::user();
        $this->ok([
            'user' => is_array($u) ? $u : null,
        ]);
    }

    public function v1Csrf(): void
    {
        $this->preflight();
        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        if ($csrfKey === '') {
            $this->ok(['csrf_key' => null, 'csrf_token' => null]);
            return;
        }

        $this->ok(['csrf_key' => $csrfKey, 'csrf_token' => CSRF::token($csrfKey)]);
    }

    public function v1AppVersion(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $apkPath = (string) ($this->config['mobile']['apk_path'] ?? '/downloads/wsm.apk');
        $apkPath = $apkPath !== '' ? '/' . ltrim($apkPath, '/') : '/downloads/wsm.apk';

        $latestVersion = (string) ($this->config['mobile']['latest_version'] ?? '');
        $latestBuild = (int) ($this->config['mobile']['latest_build'] ?? 0);

        $publicDir = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public');
        $apkFsPath = null;
        if (is_string($publicDir) && $publicDir !== '') {
            $candidate = $publicDir . DIRECTORY_SEPARATOR . ltrim($apkPath, '/');
            $resolved = realpath($candidate);
            if (is_string($resolved) && $resolved !== '' && str_starts_with($resolved, $publicDir . DIRECTORY_SEPARATOR) && is_file($resolved)) {
                $apkFsPath = $resolved;
            }
        }

        $baseUrl = isset($this->config['app']['base_url']) ? rtrim((string) $this->config['app']['base_url'], '/') : '';
        $apkUrl = $apkPath;
        if ($baseUrl !== '') {
            $apkUrl = $baseUrl . $apkPath;
        } else {
            $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
            if ($host !== '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $apkUrl = $scheme . '://' . $host . $apkPath;
            }
        }

        $apkSize = null;
        $apkUpdatedAt = null;
        $apkIsLfsPointer = null;
        $apkOk = null;
        if ($apkFsPath !== null) {
            clearstatcache(true, $apkFsPath);
            $apkSizeRaw = filesize($apkFsPath);
            $apkSize = $apkSizeRaw !== false ? (int) $apkSizeRaw : null;
            $apkUpdatedAt = gmdate('c', (int) filemtime($apkFsPath));
            $apkIsLfsPointer = $this->isGitLfsPointerFile($apkFsPath);
            $apkOk = $apkSize !== null && $apkSize >= (1024 * 1024) && $apkIsLfsPointer === false;
        }

        $this->ok([
            'latest_version' => $latestVersion !== '' ? $latestVersion : null,
            'latest_build' => $latestBuild > 0 ? $latestBuild : null,
            'apk_path' => $apkPath,
            'apk_url' => $apkUrl,
            'apk_size' => $apkSize,
            'apk_updated_at' => $apkUpdatedAt,
            'apk_is_lfs_pointer' => $apkIsLfsPointer,
            'apk_ok' => $apkOk,
        ]);
    }

    public function v1Login(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $input = $this->inputJson();
        $username = Validator::requiredString($input, 'username', 1, 60);
        $password = Validator::requiredString($input, 'password', (int) ($this->config['security']['password_min_length'] ?? 12), 255);
        if ($username === null || $password === null) {
            $this->failExit('Invalid credentials', 400, 'invalid_request');
        }

        if (!Auth::attempt($this->pdo, $username, $password)) {
            $this->failExit('Unauthorized', 401, 'unauthorized');
        }

        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        $this->ok([
            'user' => Auth::user(),
            'csrf_key' => $csrfKey !== '' ? $csrfKey : null,
            'csrf_token' => $csrfKey !== '' ? CSRF::token($csrfKey) : null,
            'auth' => ['mode' => 'cookie'],
        ]);
    }

    public function v1TokenLogin(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $input = $this->inputJson();
        $username = Validator::requiredString($input, 'username', 1, 60);
        $password = Validator::requiredString($input, 'password', (int) ($this->config['security']['password_min_length'] ?? 12), 255);
        if ($username === null || $password === null) {
            $this->failExit('Invalid credentials', 400, 'invalid_request');
        }

        if (!Auth::attempt($this->pdo, $username, $password)) {
            $this->failExit('Unauthorized', 401, 'unauthorized');
        }

        $u = Auth::user();
        $uid = is_array($u) && isset($u['id']) ? (int) $u['id'] : 0;
        if ($uid < 1) {
            $this->failExit('Unauthorized', 401, 'unauthorized');
        }

        $token = $this->issueBearerToken($uid);
        if (($token['ok'] ?? false) !== true) {
            $this->failExit('Token error', 500, 'token_error');
        }

        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        $this->ok([
            'user' => $u,
            'token' => (string) ($token['token'] ?? ''),
            'token_type' => 'Bearer',
            'expires_at' => (string) ($token['expires_at'] ?? ''),
            'csrf_key' => $csrfKey !== '' ? $csrfKey : null,
            'csrf_token' => $csrfKey !== '' ? CSRF::token($csrfKey) : null,
            'auth' => ['mode' => 'bearer'],
        ]);
    }

    public function v1Logout(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $input = $this->inputJson();
        $this->requireApiAuth();
        $this->requireApiCsrf($input);

        Auth::logout();
        $this->ok(null);
    }

    public function v1Me(): void
    {
        $this->preflight();
        $u = $this->requireApiAuth();
        $this->ok(['user' => $u]);
    }

    private function listLimitOffset(): array
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        if ($offset < 0) {
            $offset = 0;
        }

        return [$limit, $offset];
    }

    public function v1Materials(): void
    {
        $this->preflight();
        $this->requireApiAuth();

        $showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';
        $q = isset($_GET['q']) && is_string($_GET['q']) ? trim((string) $_GET['q']) : '';

        $select = "SELECT m.id, m.name, mt.name AS type_name, u.code AS unit_code, s.name AS supplier_name,
                    m.current_qty, m.unit_cost, m.purchase_url, m.min_stock, m.is_archived";
        if ($this->hasColumn('materials', 'product_code')) {
            $select .= ", m.product_code";
        }
        $select .= "
             FROM materials m
             JOIN material_types mt ON mt.id = m.material_type_id
             JOIN units u ON u.id = m.unit_id
             LEFT JOIN suppliers s ON s.id = m.supplier_id
             WHERE m.is_archived = :archived";

        $params = ['archived' => $showArchived ? 1 : 0];
        if ($q !== '') {
            $select .= " AND m.name LIKE :q";
            $params['q'] = '%' . $q . '%';
        }
        [$limit, $offset] = $this->listLimitOffset();
        $select .= " ORDER BY m.name ASC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        $stmt = $this->pdo->prepare($select);
        $stmt->execute($params);
        $materials = $stmt->fetchAll();

        $this->ok(['materials' => $materials], [
            'limit' => $limit,
            'offset' => $offset,
            'returned' => is_array($materials) ? count($materials) : 0,
        ]);
    }

    public function v1Products(): void
    {
        $this->preflight();
        $this->requireApiAuth();

        $q = isset($_GET['q']) && is_string($_GET['q']) ? trim((string) $_GET['q']) : '';

        $sql =
            "SELECT p.id, p.name, p.sku, pc.name AS category_name, p.sale_price, p.estimated_hours, p.manpower_hours, p.status, p.stock_qty
             FROM products p
             LEFT JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.is_active = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (p.name LIKE :q OR p.sku LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }
        [$limit, $offset] = $this->listLimitOffset();
        $sql .= " ORDER BY p.name ASC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        $this->ok(['products' => $products], [
            'limit' => $limit,
            'offset' => $offset,
            'returned' => is_array($products) ? count($products) : 0,
        ]);
    }

    public function v1Bom(): void
    {
        $this->preflight();
        $this->requireApiAuth();

        $productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
        if ($productId < 1) {
            $this->failExit('Invalid product_id', 400, 'invalid_request');
        }

        $p = $this->pdo->prepare("SELECT id, name, sku, manpower_hours FROM products WHERE id = ? LIMIT 1");
        $p->execute([$productId]);
        $product = $p->fetch();
        if (!$product) {
            $this->failExit('Not found', 404, 'not_found');
        }

        $materials = $this->pdo->prepare(
            "SELECT bm.id, bm.qty, bm.waste_percent, m.name AS material_name, u.code AS unit_code, m.id AS material_id
             FROM bom_materials bm
             JOIN materials m ON m.id = bm.material_id
             JOIN units u ON u.id = bm.unit_id
             WHERE bm.product_id = ?
             ORDER BY m.name ASC"
        );
        $materials->execute([$productId]);

        $machines = $this->pdo->prepare(
            "SELECT bmc.id, bmc.hours, mc.name AS machine_name, mc.power_kw, mc.is_active, mc.id AS machine_id
             FROM bom_machines bmc
             JOIN machines mc ON mc.id = bmc.machine_id
             WHERE bmc.product_id = ?
             ORDER BY mc.name ASC"
        );
        $machines->execute([$productId]);

        $this->ok([
            'product' => $product,
            'materials' => $materials->fetchAll(),
            'machines' => $machines->fetchAll(),
        ]);
    }

    public function v1ProductionOrders(): void
    {
        $this->preflight();
        $this->requireApiAuth();

        [$limit, $offset] = $this->listLimitOffset();
        $ordersStmt = $this->pdo->query(
            "SELECT po.id, po.qty, po.status, po.started_at, po.completed_at, p.name AS product_name, p.sku, u.name AS operator_name,
                    pc.total_cost, pc.cost_per_unit
             FROM production_orders po
             JOIN products p ON p.id = po.product_id
             JOIN users u ON u.id = po.operator_user_id
             LEFT JOIN production_costs pc ON pc.production_order_id = po.id
             ORDER BY po.started_at DESC
             LIMIT " . (int) $limit . " OFFSET " . (int) $offset
        );
        $orders = $ordersStmt->fetchAll();

        $this->ok(['orders' => $orders], [
            'limit' => $limit,
            'offset' => $offset,
            'returned' => is_array($orders) ? count($orders) : 0,
        ]);
    }

    public function v1ProductionStart(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $input = $this->inputJson();
        $user = $this->requireApiAuth();
        $this->requireApiCsrf($input);

        $productId = Validator::requiredInt($input, 'product_id', 1);
        $qty = Validator::requiredInt($input, 'qty', 1, 1000000);
        $notes = Validator::optionalString($input, 'notes', 2000);
        if ($productId === null || $qty === null) {
            $this->failExit('Invalid input', 400, 'invalid_request');
        }

        $product = $this->pdo->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $product->execute([$productId]);
        $p = $product->fetch();
        if (!$p) {
            $this->failExit('Product not found', 404, 'not_found');
        }

        $check = $this->validateRecipeForQty($productId, $qty);
        if ($check['ok'] !== true) {
            $this->failExit((string) $check['message'], 400, 'invalid_request');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO production_orders (product_id, qty, status, started_at, completed_at, operator_user_id, notes, created_at, updated_at)
             VALUES (?, ?, 'Pornita', UTC_TIMESTAMP(), NULL, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $stmt->execute([$productId, $qty, (int) ($user['id'] ?? 0), $notes]);

        $this->ok(['production_order_id' => (int) $this->pdo->lastInsertId()], [], 201);
    }

    public function v1ProductionFinalize(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $input = $this->inputJson();
        $this->requireApiAuth();
        $this->requireApiRole(['SuperAdmin', 'Admin', 'Operator']);
        $this->requireApiCsrf($input);

        $orderId = Validator::requiredInt($input, 'production_order_id', 1);
        if ($orderId === null) {
            $this->failExit('Invalid production_order_id', 400, 'invalid_request');
        }

        $this->pdo->beginTransaction();
        try {
            $ord = $this->pdo->prepare(
                "SELECT po.id, po.product_id, po.qty, po.status, po.operator_user_id, p.manpower_hours
                 FROM production_orders po
                 JOIN products p ON p.id = po.product_id
                 WHERE po.id = ? FOR UPDATE"
            );
            $ord->execute([$orderId]);
            $order = $ord->fetch();
            if (!$order || (string) $order['status'] !== 'Pornita') {
                throw new \RuntimeException('Stare invalida');
            }

            $productId = (int) $order['product_id'];
            $qty = (int) $order['qty'];

            $check = $this->validateRecipeForQty($productId, $qty);
            if ($check['ok'] !== true) {
                throw new \RuntimeException((string) $check['message']);
            }

            $energyCostPerKwh = (float) $this->getSettingDecimal('energy_cost_per_kwh', '1.00');
            $operatorHourly = (float) $this->getSettingDecimal('operator_hourly_cost', '0.00');

            $materials = $this->getMaterialsPlan($productId, $qty);
            $machines = $this->getMachinesPlan($productId, $qty);

            $materialsCost = 0.0;
            foreach ($materials as $line) {
                $materialId = (int) $line['material_id'];
                $qtyUsed = (float) $line['qty_used'];
                $unitCost = (float) $line['unit_cost'];
                $cost = $qtyUsed * $unitCost;

                $m = $this->pdo->prepare("SELECT current_qty FROM materials WHERE id = ? FOR UPDATE");
                $m->execute([$materialId]);
                $mat = $m->fetch();
                if (!$mat) {
                    throw new \RuntimeException('Material lipsa');
                }

                $current = (float) $mat['current_qty'];
                $next = $current - $qtyUsed;
                if ($next < -0.00001) {
                    throw new \RuntimeException('Stoc insuficient: ' . (string) $line['material_name']);
                }

                $this->pdo->prepare("UPDATE materials SET current_qty = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")
                    ->execute([(string) $next, $materialId]);

                $this->pdo->prepare(
                    "INSERT INTO material_movements (material_id, movement_type, qty, unit_cost, ref_type, ref_id, note, user_id, created_at)
                     VALUES (?, 'out', ?, ?, 'production', ?, 'Consum productie', ?, UTC_TIMESTAMP())"
                )->execute([$materialId, (string) $qtyUsed, (string) $unitCost, $orderId, (int) (Auth::user()['id'] ?? 0)]);

                $this->pdo->prepare(
                    "INSERT INTO production_material_usage (production_order_id, material_id, qty_used, unit_cost, cost, created_at)
                     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())"
                )->execute([$orderId, $materialId, (string) $qtyUsed, (string) $unitCost, (string) $cost]);

                $materialsCost += $cost;
            }

            $energyCost = 0.0;
            foreach ($machines as $line) {
                $machineId = (int) $line['machine_id'];
                $hoursUsed = (float) $line['hours_used'];
                $powerKw = (float) $line['power_kw'];
                $energyKwh = $powerKw * $hoursUsed;
                $cost = $energyKwh * $energyCostPerKwh;

                $this->pdo->prepare(
                    "INSERT INTO production_machine_usage (production_order_id, machine_id, hours_used, power_kw, energy_kwh, cost, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())"
                )->execute([$orderId, $machineId, (string) $hoursUsed, (string) $powerKw, (string) $energyKwh, (string) $cost]);

                $energyCost += $cost;
            }

            $manpowerHours = (float) $order['manpower_hours'] * $qty;
            $manpowerCost = $manpowerHours * $operatorHourly;

            $total = $materialsCost + $energyCost + $manpowerCost;
            $costPerUnit = $qty > 0 ? $total / $qty : 0.0;

            $this->pdo->prepare(
                "INSERT INTO production_costs (production_order_id, materials_cost, energy_cost, manpower_cost, total_cost, cost_per_unit, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())"
            )->execute([$orderId, (string) $materialsCost, (string) $energyCost, (string) $manpowerCost, (string) $total, (string) $costPerUnit]);

            $this->pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, status = 'Finalizat', updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$qty, $productId]);

            $this->pdo->prepare("UPDATE production_orders SET status = 'Finalizata', completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$orderId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->failExit($e->getMessage(), 400, 'invalid_request');
        }

        $this->ok(null);
    }

    public function v1Sales(): void
    {
        $this->preflight();
        $this->requireApiAuth();

        [$limit, $offset] = $this->listLimitOffset();
        $salesStmt = $this->pdo->query(
            "SELECT s.id, s.product_id, p.name AS product_name, p.sku, s.qty, s.sale_price, s.sold_at, s.customer_name, s.channel,
                    s.user_id, u.name AS user_name
             FROM sales s
             JOIN products p ON p.id = s.product_id
             JOIN users u ON u.id = s.user_id
             ORDER BY s.sold_at DESC
             LIMIT " . (int) $limit . " OFFSET " . (int) $offset
        );
        $sales = $salesStmt->fetchAll();

        $this->ok(['sales' => $sales], [
            'limit' => $limit,
            'offset' => $offset,
            'returned' => is_array($sales) ? count($sales) : 0,
        ]);
    }

    public function v1SalesCreate(): void
    {
        $this->preflight();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->failExit('Method not allowed', 405, 'method_not_allowed');
        }

        $input = $this->inputJson();
        $this->requireApiAuth();
        $this->requireApiRole(['SuperAdmin', 'Admin', 'Operator']);
        $this->requireApiCsrf($input);

        $productId = Validator::requiredInt($input, 'product_id', 1);
        $qty = Validator::requiredInt($input, 'qty', 1, 1000000);
        $salePrice = Validator::requiredDecimal($input, 'sale_price', 0);
        $salePriceCurrency = Validator::optionalString($input, 'sale_price_currency', 8);
        $customer = Validator::optionalString($input, 'customer_name', 160);
        $channel = Validator::optionalString($input, 'channel', 80);

        if ($productId === null || $qty === null || $salePrice === null) {
            $this->failExit('Invalid input', 400, 'invalid_request');
        }

        if ($salePriceCurrency === null || $salePriceCurrency === '' || !in_array($salePriceCurrency, ['lei', 'usd', 'eur'], true)) {
            $salePriceCurrency = null;
        }
        $salePriceLei = $this->moneyToLei($salePrice, $salePriceCurrency, 4);

        $this->pdo->beginTransaction();
        try {
            $p = $this->pdo->prepare("SELECT id, stock_qty FROM products WHERE id = ? FOR UPDATE");
            $p->execute([$productId]);
            $product = $p->fetch();
            if (!$product) {
                throw new \RuntimeException('Produs lipsa');
            }

            $stock = (int) $product['stock_qty'];
            if ($stock < $qty) {
                throw new \RuntimeException('Stoc insuficient');
            }

            $this->pdo->prepare(
                "INSERT INTO sales (product_id, qty, sale_price, sold_at, customer_name, channel, user_id, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?, UTC_TIMESTAMP())"
            )->execute([$productId, $qty, $salePriceLei, $customer, $channel, (int) (Auth::user()['id'] ?? 0)]);

            $next = $stock - $qty;
            $status = $next === 0 ? 'Vandut' : 'Finalizat';
            $this->pdo->prepare("UPDATE products SET stock_qty = ?, status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$next, $status, $productId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $msg = $e->getMessage() === 'Stoc insuficient' ? 'Stoc insuficient.' : 'Nu se poate salva vanzarea.';
            $this->failExit($msg, 400, 'invalid_request');
        }

        $this->ok(null, [], 201);
    }

    private function validateRecipeForQty(int $productId, int $qty): array
    {
        $materials = $this->getMaterialsPlan($productId, $qty);
        if (!$materials) {
            return ['ok' => false, 'message' => 'Reteta nu are materii prime.'];
        }

        foreach ($materials as $m) {
            if ((float) $m['current_qty'] + 0.00001 < (float) $m['qty_used']) {
                return ['ok' => false, 'message' => 'Stoc insuficient: ' . (string) $m['material_name']];
            }
        }

        $machines = $this->getMachinesPlan($productId, $qty);
        foreach ($machines as $mc) {
            if ((int) $mc['is_active'] !== 1) {
                return ['ok' => false, 'message' => 'Utilaj inactiv in reteta: ' . (string) $mc['machine_name']];
            }
        }

        return ['ok' => true, 'message' => 'OK'];
    }

    private function getMaterialsPlan(int $productId, int $qty): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id AS material_id, m.name AS material_name, m.current_qty, m.unit_cost,
                    bm.qty AS qty_per_unit, bm.waste_percent,
                    (bm.qty * :qty * (1 + (bm.waste_percent / 100))) AS qty_used
             FROM bom_materials bm
             JOIN materials m ON m.id = bm.material_id
             WHERE bm.product_id = :product_id"
        );
        $stmt->execute(['qty' => $qty, 'product_id' => $productId]);
        return $stmt->fetchAll();
    }

    private function getMachinesPlan(int $productId, int $qty): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT mc.id AS machine_id, mc.name AS machine_name, mc.power_kw, mc.is_active,
                    (bmc.hours * :qty) AS hours_used
             FROM bom_machines bmc
             JOIN machines mc ON mc.id = bmc.machine_id
             WHERE bmc.product_id = :product_id"
        );
        $stmt->execute(['qty' => $qty, 'product_id' => $productId]);
        return $stmt->fetchAll();
    }

    private function getSettingDecimal(string $key, string $fallback): string
    {
        $stmt = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $v = $row ? (string) $row['value'] : $fallback;
        $v = str_replace(',', '.', trim($v));
        return preg_match('/^-?\d+(\.\d+)?$/', $v) ? $v : $fallback;
    }
}
