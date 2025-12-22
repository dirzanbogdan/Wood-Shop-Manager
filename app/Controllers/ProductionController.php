<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class ProductionController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

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

        $csrfKey = $this->config['security']['csrf_key'];
        $this->render('production/index', [
            'title' => 'Productie',
            'orders' => $orders,
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'canEdit' => in_array((string) (Auth::user()['role'] ?? ''), ['SuperAdmin', 'Admin', 'Operator'], true),
        ]);
    }

    public function start(): void
    {
        $this->requireAuth();

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('production/start');
            }

            $productId = Validator::requiredInt($_POST, 'product_id', 1);
            $qty = Validator::requiredInt($_POST, 'qty', 1, 1000000);
            $notes = Validator::optionalString($_POST, 'notes', 2000);
            if ($productId === null || $qty === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('production/start');
            }

            $product = $this->pdo->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
            $product->execute([$productId]);
            $p = $product->fetch();
            if (!$p) {
                Flash::set('error', 'Produs lipsa.');
                $this->redirect('production/start');
            }

            $check = $this->validateRecipeForQty($productId, $qty);
            if ($check['ok'] !== true) {
                Flash::set('error', (string) $check['message']);
                $this->redirect('production/start');
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO production_orders (product_id, qty, status, started_at, completed_at, operator_user_id, notes, created_at, updated_at)
                 VALUES (?, ?, 'Pornita', UTC_TIMESTAMP(), NULL, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $stmt->execute([$productId, $qty, (int) (Auth::user()['id'] ?? 0), $notes]);

            Flash::set('success', 'Comanda de productie pornita.');
            $this->redirect('production/index');
        }

        $products = $this->pdo->query("SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        $this->render('production/start', [
            'title' => 'Pornire productie',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'products' => $products,
        ]);
    }

    public function finalize(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin', 'Operator']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $ord = $this->pdo->prepare(
                "SELECT po.id, po.product_id, po.qty, po.status, po.operator_user_id, p.manpower_hours
                 FROM production_orders po
                 JOIN products p ON p.id = po.product_id
                 WHERE po.id = ? FOR UPDATE"
            );
            $ord->execute([$id]);
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

                $this->pdo->prepare("UPDATE materials SET current_qty = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(string) $next, $materialId]);

                $this->pdo->prepare(
                    "INSERT INTO material_movements (material_id, movement_type, qty, unit_cost, ref_type, ref_id, note, user_id, created_at)
                     VALUES (?, 'out', ?, ?, 'production', ?, 'Consum productie', ?, UTC_TIMESTAMP())"
                )->execute([$materialId, (string) $qtyUsed, (string) $unitCost, $id, (int) (Auth::user()['id'] ?? 0)]);

                $this->pdo->prepare(
                    "INSERT INTO production_material_usage (production_order_id, material_id, qty_used, unit_cost, cost, created_at)
                     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())"
                )->execute([$id, $materialId, (string) $qtyUsed, (string) $unitCost, (string) $cost]);

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
                )->execute([$id, $machineId, (string) $hoursUsed, (string) $powerKw, (string) $energyKwh, (string) $cost]);

                $energyCost += $cost;
            }

            $manpowerHours = (float) $order['manpower_hours'] * $qty;
            $manpowerCost = $manpowerHours * $operatorHourly;

            $total = $materialsCost + $energyCost + $manpowerCost;
            $costPerUnit = $qty > 0 ? $total / $qty : 0.0;

            $this->pdo->prepare(
                "INSERT INTO production_costs (production_order_id, materials_cost, energy_cost, manpower_cost, total_cost, cost_per_unit, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())"
            )->execute([$id, (string) $materialsCost, (string) $energyCost, (string) $manpowerCost, (string) $total, (string) $costPerUnit]);

            $this->pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, status = 'Finalizat', updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$qty, $productId]);

            $this->pdo->prepare("UPDATE production_orders SET status = 'Finalizata', completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$id]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Flash::set('error', $e->getMessage());
            $this->redirect('production/index');
        }

        Flash::set('success', 'Productie finalizata. Stocuri si costuri actualizate.');
        $this->redirect('production/index');
    }

    public function cancel(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $stmt = $this->pdo->prepare("UPDATE production_orders SET status = 'Anulata', updated_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'Pornita'");
        $stmt->execute([$id]);

        Flash::set('success', 'Comanda anulata.');
        $this->redirect('production/index');
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
