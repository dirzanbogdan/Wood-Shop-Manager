<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class MaterialsController extends Controller
{
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

    private function ensureMaterialsProductCodeColumn(): bool
    {
        if ($this->hasColumn('materials', 'product_code')) {
            return true;
        }
        try {
            $this->pdo->exec("ALTER TABLE materials ADD COLUMN product_code VARCHAR(80) NULL AFTER name");
        } catch (\Throwable $e) {
            return false;
        }
        return $this->hasColumn('materials', 'product_code');
    }

    private function ensureMaterialsProductCodeUniqueIndex(): void
    {
        if (!$this->hasColumn('materials', 'product_code')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materials' AND INDEX_NAME = 'materials_product_code_unique'"
        );
        $stmt->execute();
        $exists = (int) (($stmt->fetch()['c'] ?? 0)) > 0;
        if ($exists) {
            return;
        }

        $dup = $this->pdo->query(
            "SELECT 1
             FROM materials
             WHERE product_code IS NOT NULL
             GROUP BY product_code
             HAVING COUNT(*) > 1
             LIMIT 1"
        )->fetch();
        if ($dup) {
            return;
        }

        try {
            $this->pdo->exec("ALTER TABLE materials ADD UNIQUE KEY materials_product_code_unique (product_code)");
        } catch (\Throwable $e) {
        }
    }

    private function findMaterialByProductCode(string $productCode): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, product_code FROM materials WHERE product_code = ? LIMIT 1");
        $stmt->execute([$productCode]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function index(): void
    {
        $this->requireAuth();

        $showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';
        $q = isset($_GET['q']) && is_string($_GET['q']) ? trim((string) $_GET['q']) : '';

        $sql =
            "SELECT m.id, m.name, mt.name AS type_name, u.code AS unit_code, s.name AS supplier_name,
                    m.current_qty, m.unit_cost, m.purchase_url, m.min_stock, m.is_archived
             FROM materials m
             JOIN material_types mt ON mt.id = m.material_type_id
             JOIN units u ON u.id = m.unit_id
             LEFT JOIN suppliers s ON s.id = m.supplier_id
             WHERE m.is_archived = :archived";

        $params = ['archived' => $showArchived ? 1 : 0];
        if ($q !== '') {
            $sql .= " AND m.name LIKE :q";
            $params['q'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY m.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $materials = $stmt->fetchAll();

        $this->render('materials/index', [
            'title' => 'Materie prima',
            'materials' => $materials,
            'q' => $q,
            'showArchived' => $showArchived,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $csrfKey = $this->config['security']['csrf_key'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('materials/create');
            }

            $name = Validator::requiredString($_POST, 'name', 1, 160);
            $typeId = Validator::requiredInt($_POST, 'material_type_id', 1);
            $unitId = Validator::requiredInt($_POST, 'unit_id', 1);
            $supplierId = Validator::optionalInt($_POST, 'supplier_id', 1);
            $currentQty = Validator::requiredDecimal($_POST, 'current_qty', 0);
            $unitCost = Validator::requiredDecimal($_POST, 'unit_cost', 0);
            $unitCostCurrency = Validator::optionalString($_POST, 'unit_cost_currency', 8);
            $minStock = Validator::requiredDecimal($_POST, 'min_stock', 0);
            $purchaseDate = Validator::optionalString($_POST, 'purchase_date', 10);
            if ($purchaseDate !== null) {
                $purchaseDate = Validator::requiredDate(['purchase_date' => $purchaseDate], 'purchase_date');
            }
            $purchaseUrl = Validator::optionalString($_POST, 'purchase_url', 500);
            $productCode = Validator::optionalString($_POST, 'product_code', 80);
            $conflictAction = isset($_POST['product_code_conflict_action']) ? (string) $_POST['product_code_conflict_action'] : '';

            if ($name === null || $typeId === null || $unitId === null || $currentQty === null || $unitCost === null || $minStock === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('materials/create');
            }

            if ($unitCostCurrency === null || $unitCostCurrency === '' || !in_array($unitCostCurrency, ['lei', 'usd', 'eur'], true)) {
                $unitCostCurrency = null;
            }
            $unitCost = $this->moneyToLei($unitCost, $unitCostCurrency, 4);

            $conflictAction = isset($_POST['product_code_conflict_action']) ? (string) $_POST['product_code_conflict_action'] : '';
            $hasProductCode = $this->ensureMaterialsProductCodeColumn();
            if ($hasProductCode) {
                $this->ensureMaterialsProductCodeUniqueIndex();
            }

            if ($hasProductCode && $productCode !== null) {
                $existing = $this->findMaterialByProductCode($productCode);
                if ($existing) {
                    if ($conflictAction !== 'overwrite') {
                        $types = $this->pdo->query("SELECT id, name FROM material_types WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
                        $units = $this->pdo->query("SELECT id, code FROM units ORDER BY code ASC")->fetchAll();
                        $suppliers = $this->pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

                        $this->render('materials/form', [
                            'title' => 'Adauga material',
                            'csrf' => CSRF::token($csrfKey),
                            'csrf_key' => $csrfKey,
                            'types' => $types,
                            'units' => $units,
                            'suppliers' => $suppliers,
                            'material' => null,
                            'form' => [
                                'name' => $name,
                                'product_code' => $productCode,
                                'material_type_id' => $typeId,
                                'supplier_id' => $supplierId,
                                'unit_id' => $unitId,
                                'current_qty' => $currentQty,
                                'unit_cost' => $unitCost,
                                'purchase_date' => $purchaseDate,
                                'purchase_url' => $purchaseUrl,
                                'min_stock' => $minStock,
                            ],
                            'product_code_conflict' => $existing,
                        ]);
                        return;
                    }

                    $this->pdo->beginTransaction();
                    try {
                        $lock = $this->pdo->prepare("SELECT id FROM materials WHERE product_code = ? LIMIT 1 FOR UPDATE");
                        $lock->execute([$productCode]);
                        $row = $lock->fetch();
                        if (!is_array($row)) {
                            throw new \RuntimeException('Material lipsa');
                        }
                        $existingId = (int) $row['id'];

                        $this->pdo->prepare(
                            "UPDATE materials
                             SET name = ?, product_code = ?, material_type_id = ?, supplier_id = ?, unit_id = ?, current_qty = ?, unit_cost = ?, purchase_date = ?, purchase_url = ?, min_stock = ?, updated_at = UTC_TIMESTAMP()
                             WHERE id = ?"
                        )->execute([
                            $name,
                            $productCode,
                            $typeId,
                            $supplierId,
                            $unitId,
                            $currentQty,
                            $unitCost,
                            $purchaseDate,
                            $purchaseUrl,
                            $minStock,
                            $existingId,
                        ]);

                        $mv = $this->pdo->prepare(
                            "INSERT INTO material_movements (material_id, movement_type, qty, unit_cost, ref_type, ref_id, note, user_id, created_at)
                             VALUES (?, 'adjust', ?, ?, 'overwrite', NULL, 'Overwrite', ?, UTC_TIMESTAMP())"
                        );
                        $mv->execute([$existingId, $currentQty, $unitCost, (int) Auth::user()['id']]);

                        $this->pdo->commit();
                    } catch (\Throwable $e) {
                        $this->pdo->rollBack();
                        Flash::set('error', 'Eroare la suprascriere.');
                        $this->redirect('materials/create');
                    }

                    Flash::set('success', 'Material suprascris.');
                    $this->redirect('materials/index');
                }
            }

            $this->pdo->beginTransaction();
            try {
                if ($hasProductCode) {
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO materials (name, product_code, material_type_id, supplier_id, unit_id, current_qty, unit_cost, purchase_date, purchase_url, min_stock, is_archived, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                    );
                    $stmt->execute([
                        $name,
                        $productCode,
                        $typeId,
                        $supplierId,
                        $unitId,
                        $currentQty,
                        $unitCost,
                        $purchaseDate,
                        $purchaseUrl,
                        $minStock,
                    ]);
                } else {
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO materials (name, material_type_id, supplier_id, unit_id, current_qty, unit_cost, purchase_date, purchase_url, min_stock, is_archived, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                    );
                    $stmt->execute([
                        $name,
                        $typeId,
                        $supplierId,
                        $unitId,
                        $currentQty,
                        $unitCost,
                        $purchaseDate,
                        $purchaseUrl,
                        $minStock,
                    ]);
                }
                $materialId = (int) $this->pdo->lastInsertId();

                if ((float) $currentQty > 0) {
                    $mv = $this->pdo->prepare(
                        "INSERT INTO material_movements (material_id, movement_type, qty, unit_cost, ref_type, ref_id, note, user_id, created_at)
                         VALUES (?, 'adjust', ?, ?, 'init', NULL, 'Initial', ?, UTC_TIMESTAMP())"
                    );
                    $mv->execute([$materialId, $currentQty, $unitCost, (int) Auth::user()['id']]);
                }

                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                if ($hasProductCode && $productCode !== null) {
                    $existing = $this->findMaterialByProductCode($productCode);
                    if ($existing) {
                        $types = $this->pdo->query("SELECT id, name FROM material_types WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
                        $units = $this->pdo->query("SELECT id, code FROM units ORDER BY code ASC")->fetchAll();
                        $suppliers = $this->pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

                        $this->render('materials/form', [
                            'title' => 'Adauga material',
                            'csrf' => CSRF::token($csrfKey),
                            'csrf_key' => $csrfKey,
                            'types' => $types,
                            'units' => $units,
                            'suppliers' => $suppliers,
                            'material' => null,
                            'form' => [
                                'name' => $name,
                                'product_code' => $productCode,
                                'material_type_id' => $typeId,
                                'supplier_id' => $supplierId,
                                'unit_id' => $unitId,
                                'current_qty' => $currentQty,
                                'unit_cost' => $unitCost,
                                'purchase_date' => $purchaseDate,
                                'purchase_url' => $purchaseUrl,
                                'min_stock' => $minStock,
                            ],
                            'product_code_conflict' => $existing,
                        ]);
                        return;
                    }
                }

                Flash::set('error', 'Eroare la salvare.');
                $this->redirect('materials/create');
            }

            Flash::set('success', 'Material adaugat.');
            $this->redirect('materials/index');
        }

        $types = $this->pdo->query("SELECT id, name FROM material_types WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        $units = $this->pdo->query("SELECT id, code FROM units ORDER BY code ASC")->fetchAll();
        $suppliers = $this->pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        $this->render('materials/form', [
            'title' => 'Adauga material',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'types' => $types,
            'units' => $units,
            'suppliers' => $suppliers,
            'material' => null,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('materials/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('materials/edit', ['id' => $id]);
            }

            $name = Validator::requiredString($_POST, 'name', 1, 160);
            $typeId = Validator::requiredInt($_POST, 'material_type_id', 1);
            $unitId = Validator::requiredInt($_POST, 'unit_id', 1);
            $supplierId = Validator::optionalInt($_POST, 'supplier_id', 1);
            $unitCost = Validator::requiredDecimal($_POST, 'unit_cost', 0);
            $unitCostCurrency = Validator::optionalString($_POST, 'unit_cost_currency', 8);
            $minStock = Validator::requiredDecimal($_POST, 'min_stock', 0);
            $purchaseDate = Validator::optionalString($_POST, 'purchase_date', 10);
            if ($purchaseDate !== null) {
                $purchaseDate = Validator::requiredDate(['purchase_date' => $purchaseDate], 'purchase_date');
            }
            $purchaseUrl = Validator::optionalString($_POST, 'purchase_url', 500);
            $productCode = Validator::optionalString($_POST, 'product_code', 80);

            if ($name === null || $typeId === null || $unitId === null || $unitCost === null || $minStock === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('materials/edit', ['id' => $id]);
            }

            if ($unitCostCurrency === null || $unitCostCurrency === '' || !in_array($unitCostCurrency, ['lei', 'usd', 'eur'], true)) {
                $unitCostCurrency = null;
            }
            $unitCost = $this->moneyToLei($unitCost, $unitCostCurrency, 4);

            $hasProductCode = $this->ensureMaterialsProductCodeColumn();
            if ($hasProductCode) {
                $this->ensureMaterialsProductCodeUniqueIndex();
            }

            if ($hasProductCode && $productCode !== null) {
                $stmt = $this->pdo->prepare("SELECT id, name, product_code FROM materials WHERE product_code = ? AND id <> ? LIMIT 1");
                $stmt->execute([$productCode, $id]);
                $existing = $stmt->fetch();
                if (is_array($existing)) {
                    if ($conflictAction !== 'overwrite') {
                        $types = $this->pdo->query("SELECT id, name FROM material_types WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
                        $units = $this->pdo->query("SELECT id, code FROM units ORDER BY code ASC")->fetchAll();
                        $suppliers = $this->pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

                        $this->render('materials/form', [
                            'title' => 'Editeaza material',
                            'csrf' => CSRF::token($csrfKey),
                            'csrf_key' => $csrfKey,
                            'types' => $types,
                            'units' => $units,
                            'suppliers' => $suppliers,
                            'material' => ['id' => $id],
                            'form' => [
                                'id' => $id,
                                'name' => $name,
                                'product_code' => $productCode,
                                'material_type_id' => $typeId,
                                'supplier_id' => $supplierId,
                                'unit_id' => $unitId,
                                'unit_cost' => $unitCost,
                                'purchase_date' => $purchaseDate,
                                'purchase_url' => $purchaseUrl,
                                'min_stock' => $minStock,
                            ],
                            'product_code_conflict' => $existing,
                        ]);
                        return;
                    }

                    try {
                        $existingId = (int) $existing['id'];
                        $this->pdo->prepare(
                            "UPDATE materials
                             SET name = ?, product_code = ?, material_type_id = ?, supplier_id = ?, unit_id = ?, unit_cost = ?, purchase_date = ?, purchase_url = ?, min_stock = ?, updated_at = UTC_TIMESTAMP()
                             WHERE id = ?"
                        )->execute([$name, $productCode, $typeId, $supplierId, $unitId, $unitCost, $purchaseDate, $purchaseUrl, $minStock, $existingId]);

                        Flash::set('success', 'Material suprascris.');
                        $this->redirect('materials/edit', ['id' => $existingId]);
                    } catch (\Throwable $e) {
                        Flash::set('error', 'Eroare la suprascriere.');
                        $this->redirect('materials/edit', ['id' => $id]);
                    }
                }
            }
            if ($hasProductCode) {
                $stmt = $this->pdo->prepare(
                    "UPDATE materials
                     SET name = ?, product_code = ?, material_type_id = ?, supplier_id = ?, unit_id = ?, unit_cost = ?, purchase_date = ?, purchase_url = ?, min_stock = ?, updated_at = UTC_TIMESTAMP()
                     WHERE id = ?"
                );
                $stmt->execute([$name, $productCode, $typeId, $supplierId, $unitId, $unitCost, $purchaseDate, $purchaseUrl, $minStock, $id]);
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE materials
                     SET name = ?, material_type_id = ?, supplier_id = ?, unit_id = ?, unit_cost = ?, purchase_date = ?, purchase_url = ?, min_stock = ?, updated_at = UTC_TIMESTAMP()
                     WHERE id = ?"
                );
                $stmt->execute([$name, $typeId, $supplierId, $unitId, $unitCost, $purchaseDate, $purchaseUrl, $minStock, $id]);
            }

            Flash::set('success', 'Material actualizat.');
            $this->redirect('materials/edit', ['id' => $id]);
        }

        $selectCols = "id, name, material_type_id, supplier_id, unit_id, current_qty, unit_cost, purchase_date, purchase_url, min_stock, is_archived";
        if ($this->hasColumn('materials', 'product_code')) {
            $selectCols = "id, name, product_code, material_type_id, supplier_id, unit_id, current_qty, unit_cost, purchase_date, purchase_url, min_stock, is_archived";
        }
        $stmt = $this->pdo->prepare("SELECT " . $selectCols . " FROM materials WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        if (!$material) {
            $this->redirect('materials/index');
        }

        $types = $this->pdo->query("SELECT id, name FROM material_types WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        $units = $this->pdo->query("SELECT id, code FROM units ORDER BY code ASC")->fetchAll();
        $suppliers = $this->pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        $this->render('materials/form', [
            'title' => 'Editeaza material',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'types' => $types,
            'units' => $units,
            'suppliers' => $suppliers,
            'material' => $material,
        ]);
    }

    public function archive(): void
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

        $stmt = $this->pdo->prepare("UPDATE materials SET is_archived = 1, updated_at = UTC_TIMESTAMP() WHERE id = ?");
        $stmt->execute([$id]);

        Flash::set('success', 'Material arhivat.');
        $this->redirect('materials/index');
    }

    public function movements(): void
    {
        $this->requireAuth();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('materials/index');
        }

        $m = $this->pdo->prepare("SELECT id, name FROM materials WHERE id = ? LIMIT 1");
        $m->execute([$id]);
        $material = $m->fetch();
        if (!$material) {
            $this->redirect('materials/index');
        }

        $stmt = $this->pdo->prepare(
            "SELECT mm.id, mm.movement_type, mm.qty, mm.unit_cost, mm.ref_type, mm.ref_id, mm.note, mm.created_at, u.name AS user_name
             FROM material_movements mm
             JOIN users u ON u.id = mm.user_id
             WHERE mm.material_id = ?
             ORDER BY mm.created_at DESC
             LIMIT 200"
        );
        $stmt->execute([$id]);
        $movements = $stmt->fetchAll();

        $csrfKey = $this->config['security']['csrf_key'];

        $this->render('materials/movements', [
            'title' => 'Istoric miscari',
            'material' => $material,
            'movements' => $movements,
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
        ]);
    }

    public function adjust(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('materials/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
            http_response_code(400);
            echo 'Cerere invalida.';
            return;
        }

        $type = isset($_POST['movement_type']) ? (string) $_POST['movement_type'] : '';
        if (!in_array($type, ['in', 'out', 'adjust'], true)) {
            Flash::set('error', 'Tip miscare invalid.');
            $this->redirect('materials/movements', ['id' => $id]);
        }

        $qty = Validator::requiredDecimal($_POST, 'qty', 0.0001);
        $unitCost = Validator::optionalString($_POST, 'unit_cost', 30);
        $unitCostCurrency = Validator::optionalString($_POST, 'unit_cost_currency', 8);
        $note = Validator::optionalString($_POST, 'note', 1000);
        if ($qty === null) {
            Flash::set('error', 'Cantitate invalida.');
            $this->redirect('materials/movements', ['id' => $id]);
        }

        $this->pdo->beginTransaction();
        try {
            $m = $this->pdo->prepare("SELECT id, current_qty FROM materials WHERE id = ? FOR UPDATE");
            $m->execute([$id]);
            $material = $m->fetch();
            if (!$material) {
                throw new \RuntimeException('Material lipsa');
            }

            $current = (float) $material['current_qty'];
            $delta = (float) $qty;
            if ($type === 'out') {
                $delta *= -1;
            }
            if ($type === 'adjust') {
                $delta = ((float) $qty) - $current;
            }

            $next = $current + $delta;
            if ($next < -0.00001) {
                throw new \RuntimeException('Stoc negativ');
            }

            $this->pdo->prepare("UPDATE materials SET current_qty = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(string) $next, $id]);

            $uc = null;
            if (is_string($unitCost)) {
                $unitCost = str_replace(',', '.', trim($unitCost));
                if ($unitCost !== '' && preg_match('/^-?\d+(\.\d+)?$/', $unitCost)) {
                    if ($unitCostCurrency === null || $unitCostCurrency === '' || !in_array($unitCostCurrency, ['lei', 'usd', 'eur'], true)) {
                        $unitCostCurrency = null;
                    }
                    $uc = $this->moneyToLei($unitCost, $unitCostCurrency, 4);
                }
            }

            $mv = $this->pdo->prepare(
                "INSERT INTO material_movements (material_id, movement_type, qty, unit_cost, ref_type, ref_id, note, user_id, created_at)
                 VALUES (?, ?, ?, ?, 'manual', NULL, ?, ?, UTC_TIMESTAMP())"
            );
            $mv->execute([$id, $type, $qty, $uc, $note, (int) Auth::user()['id']]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Flash::set('error', 'Nu se poate aplica miscarea (stoc insuficient?).');
            $this->redirect('materials/movements', ['id' => $id]);
        }

        Flash::set('success', 'Miscare salvata.');
        $this->redirect('materials/movements', ['id' => $id]);
    }
}
