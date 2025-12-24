<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Validator;

final class ReportsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->render('reports/index', [
            'title' => 'Rapoarte',
        ]);
    }

    public function stockMaterials(): void
    {
        $this->requireAuth();

        $rows = $this->pdo->query(
            "SELECT mt.name AS type_name, m.name AS material_name, s.name AS supplier_name,
                    u.code AS unit_code, m.current_qty, m.unit_cost, (m.current_qty * m.unit_cost) AS stock_value,
                    m.min_stock, (m.current_qty <= m.min_stock AND m.min_stock > 0) AS is_critical
             FROM materials m
             JOIN material_types mt ON mt.id = m.material_type_id
             JOIN units u ON u.id = m.unit_id
             LEFT JOIN suppliers s ON s.id = m.supplier_id
             WHERE m.is_archived = 0
             ORDER BY is_critical DESC, mt.name ASC, m.name ASC"
        )->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('stoc_materie_prima.csv', [
                'Tip', 'Material', 'Furnizor', 'UM', 'Cantitate', 'Cost unitar', 'Valoare', 'Minim', 'Critic',
            ], array_map(static function (array $r): array {
                return [
                    (string) $r['type_name'],
                    (string) $r['material_name'],
                    (string) ($r['supplier_name'] ?? ''),
                    (string) $r['unit_code'],
                    (string) $r['current_qty'],
                    (string) $r['unit_cost'],
                    (string) $r['stock_value'],
                    (string) $r['min_stock'],
                    ((int) $r['is_critical'] === 1) ? 'DA' : 'NU',
                ];
            }, $rows));
            return;
        }

        $this->render('reports/stock_materials', [
            'title' => 'Stoc materie prima',
            'rows' => $rows,
        ]);
    }

    public function stockProducts(): void
    {
        $this->requireAuth();

        $rows = $this->pdo->query(
            "SELECT p.name, p.sku, pc.name AS category_name, p.status, p.stock_qty, p.sale_price
             FROM products p
             LEFT JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.is_active = 1
             ORDER BY p.stock_qty DESC, p.name ASC"
        )->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('stoc_produse.csv', ['Produs', 'SKU', 'Categorie', 'Status', 'Stoc', 'Pret'], array_map(static function (array $r): array {
                return [
                    (string) $r['name'],
                    (string) $r['sku'],
                    (string) ($r['category_name'] ?? ''),
                    (string) $r['status'],
                    (string) $r['stock_qty'],
                    (string) $r['sale_price'],
                ];
            }, $rows));
            return;
        }

        $this->render('reports/stock_products', [
            'title' => 'Produse finite disponibile',
            'rows' => $rows,
        ]);
    }

    public function materialsConsumption(): void
    {
        $this->requireAuth();

        [$from, $to, $range] = $this->dateRange();
        $productId = Validator::optionalInt($_GET, 'product_id', 1);

        $sql = "SELECT m.name AS material_name, mt.name AS type_name, u.code AS unit_code,
                       SUM(pmu.qty_used) AS qty_used, SUM(pmu.cost) AS cost
                FROM production_material_usage pmu
                JOIN materials m ON m.id = pmu.material_id
                JOIN material_types mt ON mt.id = m.material_type_id
                JOIN units u ON u.id = m.unit_id
                JOIN production_orders po ON po.id = pmu.production_order_id
                WHERE pmu.created_at >= :from AND pmu.created_at < :to";
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
        if ($productId !== null) {
            $sql .= " AND po.product_id = :product_id";
            $params['product_id'] = $productId;
        }
        $sql .= " GROUP BY pmu.material_id
                  ORDER BY cost DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('consum_materie_prima.csv', ['Material', 'Tip', 'UM', 'Cantitate', 'Cost'], array_map(static function (array $r): array {
                return [
                    (string) $r['material_name'],
                    (string) $r['type_name'],
                    (string) $r['unit_code'],
                    (string) $r['qty_used'],
                    (string) $r['cost'],
                ];
            }, $rows));
            return;
        }

        $this->render('reports/materials_consumption', [
            'title' => 'Consum materie prima',
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'products' => $this->productsList(),
            'product_id' => $productId,
        ]);
    }

    public function energyConsumption(): void
    {
        $this->requireAuth();

        [$from, $to, $range] = $this->dateRange();
        $productId = Validator::optionalInt($_GET, 'product_id', 1);

        $sql = "SELECT mc.name AS machine_name,
                       SUM(pmu.hours_used) AS hours_used,
                       SUM(pmu.energy_kwh) AS energy_kwh,
                       SUM(pmu.cost) AS cost
                FROM production_machine_usage pmu
                JOIN machines mc ON mc.id = pmu.machine_id
                JOIN production_orders po ON po.id = pmu.production_order_id
                WHERE pmu.created_at >= :from AND pmu.created_at < :to";
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
        if ($productId !== null) {
            $sql .= " AND po.product_id = :product_id";
            $params['product_id'] = $productId;
        }
        $sql .= " GROUP BY pmu.machine_id
                  ORDER BY cost DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('consum_energie.csv', ['Utilaj', 'Ore', 'kWh', 'Cost'], array_map(static function (array $r): array {
                return [
                    (string) $r['machine_name'],
                    (string) $r['hours_used'],
                    (string) $r['energy_kwh'],
                    (string) $r['cost'],
                ];
            }, $rows));
            return;
        }

        $this->render('reports/energy_consumption', [
            'title' => 'Consum energie electrica',
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'products' => $this->productsList(),
            'product_id' => $productId,
        ]);
    }

    public function hours(): void
    {
        $this->requireAuth();

        [$from, $to, $range] = $this->dateRange();
        $productId = Validator::optionalInt($_GET, 'product_id', 1);

        $hourly = (float) $this->getSettingDecimal('operator_hourly_cost', '0.00');

        $sql = "SELECT u.name AS operator_name,
                       SUM(p.manpower_hours * po.qty) AS hours_worked,
                       SUM(p.manpower_hours * po.qty) * :hourly AS cost
                FROM production_orders po
                JOIN users u ON u.id = po.operator_user_id
                JOIN products p ON p.id = po.product_id
                WHERE po.status = 'Finalizata' AND po.completed_at >= :from AND po.completed_at < :to";
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59', 'hourly' => $hourly];
        if ($productId !== null) {
            $sql .= " AND po.product_id = :product_id";
            $params['product_id'] = $productId;
        }
        $sql .= " GROUP BY po.operator_user_id
                  ORDER BY hours_worked DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('ore_lucrate.csv', ['Operator', 'Ore', 'Cost (estimativ)'], array_map(static function (array $r): array {
                return [
                    (string) $r['operator_name'],
                    (string) $r['hours_worked'],
                    (string) $r['cost'],
                ];
            }, $rows));
            return;
        }

        $this->render('reports/hours', [
            'title' => 'Ore lucrate',
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'hourly' => $hourly,
            'products' => $this->productsList(),
            'product_id' => $productId,
        ]);
    }

    public function monthlyCost(): void
    {
        $this->requireAuth();

        [$from, $to, $range] = $this->dateRange();
        $productId = Validator::optionalInt($_GET, 'product_id', 1);

        $sql = "SELECT DATE_FORMAT(po.completed_at, '%Y-%m') AS ym,
                       SUM(pc.materials_cost) AS materials_cost,
                       SUM(pc.energy_cost) AS energy_cost,
                       SUM(pc.manpower_cost) AS manpower_cost,
                       SUM(pc.total_cost) AS total_cost
                FROM production_orders po
                JOIN production_costs pc ON pc.production_order_id = po.id
                WHERE po.status = 'Finalizata' AND po.completed_at >= :from AND po.completed_at < :to";
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
        if ($productId !== null) {
            $sql .= " AND po.product_id = :product_id";
            $params['product_id'] = $productId;
        }
        $sql .= " GROUP BY ym
                  ORDER BY ym DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('cost_productie_lunar.csv', ['Luna', 'Materii', 'Energie', 'Manpower', 'Total'], array_map(static function (array $r): array {
                return [
                    (string) $r['ym'],
                    (string) $r['materials_cost'],
                    (string) $r['energy_cost'],
                    (string) $r['manpower_cost'],
                    (string) $r['total_cost'],
                ];
            }, $rows));
            return;
        }

        $this->render('reports/monthly_cost', [
            'title' => 'Cost total productie (lunar)',
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'products' => $this->productsList(),
            'product_id' => $productId,
        ]);
    }

    public function profit(): void
    {
        $this->requireAuth();

        $entityType = $this->getSettingString('entity_type', 'srl');
        if (!in_array($entityType, ['srl', 'other'], true)) {
            $entityType = 'srl';
        }
        $taxType = $this->getSettingString('tax_type', $entityType === 'srl' ? 'income_1' : 'income');
        $taxValue = (float) $this->getSettingDecimal('tax_value', '0');

        $taxMode = 'income';
        $taxRate = 0.01;
        if ($entityType === 'srl') {
            if ($taxType === 'income_3') {
                $taxMode = 'income';
                $taxRate = 0.03;
            } elseif ($taxType === 'profit_16') {
                $taxMode = 'profit';
                $taxRate = 0.16;
            } else {
                $taxMode = 'income';
                $taxRate = 0.01;
            }
        } else {
            $taxRate = max(0.0, min(1.0, $taxValue / 100.0));
            $taxMode = $taxType === 'profit' ? 'profit' : 'income';
        }

        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.sku,
                    p.sale_price AS pret_unit,
                    COALESCE(avg_cost.avg_cost_per_unit, 0) AS avg_cost_per_unit,
                    COALESCE(avg_cost.avg_materials_cost_per_unit, 0) AS avg_materials_cost_per_unit,
                    CASE
                      WHEN :tax_mode = 'income' THEN (p.sale_price * :tax_rate)
                      WHEN :tax_mode = 'profit' THEN (GREATEST(p.sale_price - COALESCE(avg_cost.avg_materials_cost_per_unit, 0), 0) * :tax_rate)
                      ELSE 0
                    END AS impozit,
                    (p.sale_price - COALESCE(avg_cost.avg_cost_per_unit, 0)) AS marja,
                    COALESCE(s.sum_qty, 0) AS cant_vanduta,
                    (COALESCE(s.sum_qty, 0) * p.sale_price) AS valoare_vanzare,
                    ((p.sale_price - COALESCE(avg_cost.avg_cost_per_unit, 0) -
                      CASE
                        WHEN :tax_mode = 'income' THEN (p.sale_price * :tax_rate)
                        WHEN :tax_mode = 'profit' THEN (GREATEST(p.sale_price - COALESCE(avg_cost.avg_materials_cost_per_unit, 0), 0) * :tax_rate)
                        ELSE 0
                      END
                    ) * COALESCE(s.sum_qty, 0)) AS profit_net
             FROM products p
             LEFT JOIN (
                SELECT po.product_id,
                       AVG(pc.cost_per_unit) AS avg_cost_per_unit,
                       AVG(CASE WHEN po.qty > 0 THEN (pc.materials_cost / po.qty) ELSE 0 END) AS avg_materials_cost_per_unit
                FROM production_orders po
                JOIN production_costs pc ON pc.production_order_id = po.id
                WHERE po.status = 'Finalizata'
                GROUP BY po.product_id
             ) avg_cost ON avg_cost.product_id = p.id
             LEFT JOIN (
                SELECT product_id, SUM(qty) AS sum_qty
                FROM sales
                GROUP BY product_id
             ) s ON s.product_id = p.id
             WHERE p.is_active = 1
             ORDER BY profit_net ASC, p.name ASC"
        );
        $stmt->execute(['tax_mode' => $taxMode, 'tax_rate' => $taxRate]);
        $rows = $stmt->fetchAll();

        if ($this->wantsCsv()) {
            $this->csv('profit_estimare.csv', ['Produs', 'SKU', 'Pret/Unit', 'Cost mediu/unit', 'Impozit', 'Marja', 'Cant vanduta', 'Valoare vanzare', 'Profit Net'], array_map(static function (array $r): array {
                return [
                    (string) $r['name'],
                    (string) $r['sku'],
                    (string) $r['pret_unit'],
                    (string) $r['avg_cost_per_unit'],
                    (string) $r['impozit'],
                    (string) $r['marja'],
                    (string) $r['cant_vanduta'],
                    (string) $r['valoare_vanzare'],
                    (string) $r['profit_net'],
                ];
            }, $rows));
            return;
        }

        $this->render('reports/profit', [
            'title' => 'Profit estimat',
            'rows' => $rows,
        ]);
    }

    private function wantsCsv(): bool
    {
        return isset($_GET['export']) && $_GET['export'] === 'csv';
    }

    private function csv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function dateRange(): array
    {
        $range = isset($_GET['range']) && is_string($_GET['range']) ? trim($_GET['range']) : '';
        $from = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : '';
        $to = isset($_GET['to']) && is_string($_GET['to']) ? $_GET['to'] : '';

        $today = date('Y-m-d');
        $rangeFromTo = $this->rangeToDates($range, $today);
        if ($rangeFromTo !== null) {
            return [$rangeFromTo[0], $rangeFromTo[1], $range];
        }

        $fromV = Validator::requiredDate(['from' => $from], 'from');
        $toV = Validator::requiredDate(['to' => $to], 'to');
        if ($fromV === null || $toV === null) {
            $range = '30d';
            $rangeFromTo = $this->rangeToDates($range, $today);
            return [$rangeFromTo[0], $rangeFromTo[1], $range];
        }
        return [$fromV, $toV, ''];
    }

    private function rangeToDates(string $range, string $today): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $today = date('Y-m-d');
        }

        return match ($range) {
            '7d' => [date('Y-m-d', strtotime($today . ' -6 days')), $today],
            '30d' => [date('Y-m-d', strtotime($today . ' -29 days')), $today],
            'this_month' => [date('Y-m-01', strtotime($today)), $today],
            'last_month' => [
                date('Y-m-01', strtotime(date('Y-m-01', strtotime($today)) . ' -1 month')),
                date('Y-m-t', strtotime(date('Y-m-01', strtotime($today)) . ' -1 month')),
            ],
            'this_year' => [date('Y-01-01', strtotime($today)), $today],
            'last_year' => [
                (date('Y', strtotime($today)) - 1) . '-01-01',
                (date('Y', strtotime($today)) - 1) . '-12-31',
            ],
            default => null,
        };
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

    private function getSettingString(string $key, string $fallback): string
    {
        $stmt = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $v = $row ? trim((string) $row['value']) : $fallback;
        return $v !== '' ? $v : $fallback;
    }

    private function productsList(): array
    {
        return $this->pdo->query(
            "SELECT id, name, sku
             FROM products
             WHERE is_active = 1
             ORDER BY name ASC"
        )->fetchAll();
    }
}
