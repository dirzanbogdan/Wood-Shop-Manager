<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Validator;

final class ApiController extends Controller
{
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function jsonExit(array $payload, int $status = 200): void
    {
        $this->json($payload, $status);
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

    private function requireApiAuth(): array
    {
        if (!Auth::check()) {
            $this->jsonExit(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        $u = Auth::user();
        return is_array($u) ? $u : [];
    }

    private function requireApiRole(array $roles): void
    {
        $u = Auth::user();
        $role = is_array($u) && isset($u['role']) ? (string) $u['role'] : '';
        if (!in_array($role, $roles, true)) {
            $this->jsonExit(['ok' => false, 'error' => 'Forbidden'], 403);
        }
    }

    private function requireApiCsrf(array $input = []): void
    {
        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        if ($csrfKey === '') {
            return;
        }

        $header = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']) : '';
        $token = $header !== '' ? $header : (isset($input[$csrfKey]) ? (string) $input[$csrfKey] : '');
        if (!CSRF::verify($csrfKey, $token !== '' ? $token : null)) {
            $this->jsonExit(['ok' => false, 'error' => 'Invalid CSRF'], 400);
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
        $u = Auth::user();
        $this->json([
            'ok' => true,
            'version' => (string) ($this->config['app']['version'] ?? ''),
            'user' => is_array($u) ? $u : null,
            'utc' => gmdate('c'),
        ]);
    }

    public function v1Csrf(): void
    {
        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        if ($csrfKey === '') {
            $this->json(['ok' => true, 'csrf_key' => null, 'csrf_token' => null]);
            return;
        }

        $this->json([
            'ok' => true,
            'csrf_key' => $csrfKey,
            'csrf_token' => CSRF::token($csrfKey),
        ]);
    }

    public function v1Login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $input = $this->inputJson();
        $username = Validator::requiredString($input, 'username', 1, 60);
        $password = Validator::requiredString($input, 'password', (int) ($this->config['security']['password_min_length'] ?? 12), 255);
        if ($username === null || $password === null) {
            $this->jsonExit(['ok' => false, 'error' => 'Invalid credentials'], 400);
        }

        if (!Auth::attempt($this->pdo, $username, $password)) {
            $this->jsonExit(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $csrfKey = (string) ($this->config['security']['csrf_key'] ?? '');
        $this->json([
            'ok' => true,
            'user' => Auth::user(),
            'csrf_key' => $csrfKey !== '' ? $csrfKey : null,
            'csrf_token' => $csrfKey !== '' ? CSRF::token($csrfKey) : null,
        ]);
    }

    public function v1Logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $input = $this->inputJson();
        $this->requireApiAuth();
        $this->requireApiCsrf($input);

        Auth::logout();
        $this->json(['ok' => true]);
    }

    public function v1Me(): void
    {
        $u = $this->requireApiAuth();
        $this->json(['ok' => true, 'user' => $u]);
    }

    public function v1Materials(): void
    {
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
        $select .= " ORDER BY m.name ASC";

        $stmt = $this->pdo->prepare($select);
        $stmt->execute($params);
        $materials = $stmt->fetchAll();

        $this->json(['ok' => true, 'materials' => $materials]);
    }

    public function v1Products(): void
    {
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
        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        $this->json(['ok' => true, 'products' => $products]);
    }

    public function v1Bom(): void
    {
        $this->requireApiAuth();

        $productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
        if ($productId < 1) {
            $this->jsonExit(['ok' => false, 'error' => 'Invalid product_id'], 400);
        }

        $p = $this->pdo->prepare("SELECT id, name, sku, manpower_hours FROM products WHERE id = ? LIMIT 1");
        $p->execute([$productId]);
        $product = $p->fetch();
        if (!$product) {
            $this->jsonExit(['ok' => false, 'error' => 'Not found'], 404);
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

        $this->json([
            'ok' => true,
            'product' => $product,
            'materials' => $materials->fetchAll(),
            'machines' => $machines->fetchAll(),
        ]);
    }

    public function v1ProductionOrders(): void
    {
        $this->requireApiAuth();

        $orders = $this->pdo->query(
            "SELECT po.id, po.qty, po.status, po.started_at, po.completed_at, p.name AS product_name, p.sku, u.name AS operator_name,
                    pc.total_cost, pc.cost_per_unit
             FROM production_orders po
             JOIN products p ON p.id = po.product_id
             JOIN users u ON u.id = po.operator_user_id
             LEFT JOIN production_costs pc ON pc.production_order_id = po.id
             ORDER BY po.started_at DESC
             LIMIT 200"
        )->fetchAll();

        $this->json(['ok' => true, 'orders' => $orders]);
    }

    public function v1ProductionStart(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $input = $this->inputJson();
        $user = $this->requireApiAuth();
        $this->requireApiCsrf($input);

        $productId = Validator::requiredInt($input, 'product_id', 1);
        $qty = Validator::requiredInt($input, 'qty', 1, 1000000);
        $notes = Validator::optionalString($input, 'notes', 2000);
        if ($productId === null || $qty === null) {
            $this->jsonExit(['ok' => false, 'error' => 'Invalid input'], 400);
        }

        $product = $this->pdo->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $product->execute([$productId]);
        $p = $product->fetch();
        if (!$p) {
            $this->jsonExit(['ok' => false, 'error' => 'Product not found'], 404);
        }

        $check = $this->validateRecipeForQty($productId, $qty);
        if ($check['ok'] !== true) {
            $this->jsonExit(['ok' => false, 'error' => (string) $check['message']], 400);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO production_orders (product_id, qty, status, started_at, completed_at, operator_user_id, notes, created_at, updated_at)
             VALUES (?, ?, 'Pornita', UTC_TIMESTAMP(), NULL, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $stmt->execute([$productId, $qty, (int) ($user['id'] ?? 0), $notes]);

        $this->json(['ok' => true, 'production_order_id' => (int) $this->pdo->lastInsertId()]);
    }

    public function v1ProductionFinalize(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $input = $this->inputJson();
        $this->requireApiAuth();
        $this->requireApiRole(['SuperAdmin', 'Admin', 'Operator']);
        $this->requireApiCsrf($input);

        $orderId = Validator::requiredInt($input, 'production_order_id', 1);
        if ($orderId === null) {
            $this->jsonExit(['ok' => false, 'error' => 'Invalid production_order_id'], 400);
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
            $this->jsonExit(['ok' => false, 'error' => $e->getMessage()], 400);
        }

        $this->json(['ok' => true]);
    }

    public function v1Sales(): void
    {
        $this->requireApiAuth();

        $sales = $this->pdo->query(
            "SELECT s.id, s.product_id, p.name AS product_name, p.sku, s.qty, s.sale_price, s.sold_at, s.customer_name, s.channel,
                    s.user_id, u.name AS user_name
             FROM sales s
             JOIN products p ON p.id = s.product_id
             JOIN users u ON u.id = s.user_id
             ORDER BY s.sold_at DESC
             LIMIT 200"
        )->fetchAll();

        $this->json(['ok' => true, 'sales' => $sales]);
    }

    public function v1SalesCreate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(['ok' => false, 'error' => 'Method not allowed'], 405);
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
            $this->jsonExit(['ok' => false, 'error' => 'Invalid input'], 400);
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
            $this->jsonExit(['ok' => false, 'error' => $msg], 400);
        }

        $this->json(['ok' => true]);
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
