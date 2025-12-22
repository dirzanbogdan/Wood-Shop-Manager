<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class BomController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $products = $this->pdo->query(
            "SELECT p.id, p.name, p.sku,
                    (SELECT COUNT(*) FROM bom_materials bm WHERE bm.product_id = p.id) AS materials_cnt,
                    (SELECT COUNT(*) FROM bom_machines bmc WHERE bmc.product_id = p.id) AS machines_cnt
             FROM products p
             WHERE p.is_active = 1
             ORDER BY p.name ASC"
        )->fetchAll();

        $this->render('bom/index', [
            'title' => 'Retete/BOM',
            'products' => $products,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('bom/index');
        }

        $p = $this->pdo->prepare("SELECT id, name, sku, manpower_hours FROM products WHERE id = ? LIMIT 1");
        $p->execute([$id]);
        $product = $p->fetch();
        if (!$product) {
            $this->redirect('bom/index');
        }

        $materials = $this->pdo->prepare(
            "SELECT bm.id, bm.qty, bm.waste_percent, m.name AS material_name, u.code AS unit_code, m.id AS material_id
             FROM bom_materials bm
             JOIN materials m ON m.id = bm.material_id
             JOIN units u ON u.id = bm.unit_id
             WHERE bm.product_id = ?
             ORDER BY m.name ASC"
        );
        $materials->execute([$id]);

        $machines = $this->pdo->prepare(
            "SELECT bmc.id, bmc.hours, mc.name AS machine_name, mc.power_kw, mc.is_active
             FROM bom_machines bmc
             JOIN machines mc ON mc.id = bmc.machine_id
             WHERE bmc.product_id = ?
             ORDER BY mc.name ASC"
        );
        $machines->execute([$id]);

        $allMaterials = $this->pdo->query(
            "SELECT m.id, m.name, u.code AS unit_code, m.unit_id
             FROM materials m
             JOIN units u ON u.id = m.unit_id
             WHERE m.is_archived = 0
             ORDER BY m.name ASC"
        )->fetchAll();

        $allMachines = $this->pdo->query(
            "SELECT id, name, power_kw, is_active
             FROM machines
             ORDER BY is_active DESC, name ASC"
        )->fetchAll();

        $csrfKey = $this->config['security']['csrf_key'];

        $this->render('bom/edit', [
            'title' => 'Reteta/BOM',
            'product' => $product,
            'materials' => $materials->fetchAll(),
            'machines' => $machines->fetchAll(),
            'allMaterials' => $allMaterials,
            'allMachines' => $allMachines,
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'canEdit' => in_array((string) (Auth::user()['role'] ?? ''), ['SuperAdmin', 'Admin'], true),
        ]);
    }

    public function addMaterial(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($productId < 1) {
            $this->redirect('bom/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $materialId = Validator::requiredInt($_POST, 'material_id', 1);
        $qty = Validator::requiredDecimal($_POST, 'qty', 0.0001);
        $waste = Validator::requiredDecimal($_POST, 'waste_percent', 0);

        if ($materialId === null || $qty === null || $waste === null) {
            Flash::set('error', 'Campuri invalide.');
            $this->redirect('bom/edit', ['id' => $productId]);
        }

        $m = $this->pdo->prepare("SELECT id, unit_id FROM materials WHERE id = ? LIMIT 1");
        $m->execute([$materialId]);
        $row = $m->fetch();
        if (!$row) {
            Flash::set('error', 'Material lipsa.');
            $this->redirect('bom/edit', ['id' => $productId]);
        }

        $unitId = (int) $row['unit_id'];

        $stmt = $this->pdo->prepare(
            "INSERT INTO bom_materials (product_id, material_id, qty, unit_id, waste_percent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE qty = VALUES(qty), unit_id = VALUES(unit_id), waste_percent = VALUES(waste_percent), updated_at = UTC_TIMESTAMP()"
        );
        $stmt->execute([$productId, $materialId, $qty, $unitId, $waste]);

        Flash::set('success', 'Material in reteta salvat.');
        $this->redirect('bom/edit', ['id' => $productId]);
    }

    public function deleteMaterial(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $bomId = isset($_GET['bom_id']) ? (int) $_GET['bom_id'] : 0;
        $csrfKey = $this->config['security']['csrf_key'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $stmt = $this->pdo->prepare("DELETE FROM bom_materials WHERE id = ? AND product_id = ?");
        $stmt->execute([$bomId, $productId]);

        Flash::set('success', 'Linie stearsa.');
        $this->redirect('bom/edit', ['id' => $productId]);
    }

    public function addMachine(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($productId < 1) {
            $this->redirect('bom/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $machineId = Validator::requiredInt($_POST, 'machine_id', 1);
        $hours = Validator::requiredDecimal($_POST, 'hours', 0.01);
        if ($machineId === null || $hours === null) {
            Flash::set('error', 'Campuri invalide.');
            $this->redirect('bom/edit', ['id' => $productId]);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO bom_machines (product_id, machine_id, hours, created_at, updated_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE hours = VALUES(hours), updated_at = UTC_TIMESTAMP()"
        );
        $stmt->execute([$productId, $machineId, $hours]);

        Flash::set('success', 'Utilaj in reteta salvat.');
        $this->redirect('bom/edit', ['id' => $productId]);
    }

    public function deleteMachine(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $bomId = isset($_GET['bom_id']) ? (int) $_GET['bom_id'] : 0;
        $csrfKey = $this->config['security']['csrf_key'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $stmt = $this->pdo->prepare("DELETE FROM bom_machines WHERE id = ? AND product_id = ?");
        $stmt->execute([$bomId, $productId]);

        Flash::set('success', 'Linie stearsa.');
        $this->redirect('bom/edit', ['id' => $productId]);
    }
}

