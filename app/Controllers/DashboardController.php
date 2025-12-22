<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $critical = $this->pdo->query(
            "SELECT m.id, m.name, m.current_qty, m.min_stock, u.code AS unit_code
             FROM materials m
             JOIN units u ON u.id = m.unit_id
             WHERE m.is_archived = 0 AND m.min_stock > 0 AND m.current_qty <= m.min_stock
             ORDER BY (m.current_qty - m.min_stock) ASC, m.name ASC
             LIMIT 20"
        )->fetchAll();

        $inProgress = $this->pdo->query(
            "SELECT po.id, po.qty, po.started_at, p.name AS product_name, u.name AS operator_name
             FROM production_orders po
             JOIN products p ON p.id = po.product_id
             JOIN users u ON u.id = po.operator_user_id
             WHERE po.status = 'Pornita'
             ORDER BY po.started_at DESC
             LIMIT 20"
        )->fetchAll();

        $energy30 = $this->pdo->query(
            "SELECT COALESCE(SUM(pmu.energy_kwh), 0) AS kwh, COALESCE(SUM(pmu.cost), 0) AS cost
             FROM production_machine_usage pmu
             WHERE pmu.created_at >= (UTC_TIMESTAMP() - INTERVAL 30 DAY)"
        )->fetch();

        $lowProfit = $this->pdo->query(
            "SELECT p.id, p.name, p.sku, p.sale_price,
                    COALESCE(avg_cost.avg_cost_per_unit, 0) AS avg_cost_per_unit,
                    (p.sale_price - COALESCE(avg_cost.avg_cost_per_unit, 0)) AS margin
             FROM products p
             LEFT JOIN (
                SELECT po.product_id, AVG(pc.cost_per_unit) AS avg_cost_per_unit
                FROM production_orders po
                JOIN production_costs pc ON pc.production_order_id = po.id
                WHERE po.status = 'Finalizata'
                GROUP BY po.product_id
             ) avg_cost ON avg_cost.product_id = p.id
             WHERE p.is_active = 1
             ORDER BY margin ASC
             LIMIT 20"
        )->fetchAll();

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'critical' => $critical,
            'inProgress' => $inProgress,
            'energy30' => $energy30 ?: ['kwh' => 0, 'cost' => 0],
            'lowProfit' => $lowProfit,
        ]);
    }
}

