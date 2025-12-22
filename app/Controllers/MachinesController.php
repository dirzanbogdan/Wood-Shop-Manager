<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class MachinesController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $energyCost = $this->getSettingDecimal('energy_cost_per_kwh', '1.00');

        $machines = $this->pdo->query(
            "SELECT id, name, power_kw, is_active
             FROM machines
             ORDER BY is_active DESC, name ASC"
        )->fetchAll();

        $this->render('machines/index', [
            'title' => 'Utilaje',
            'machines' => $machines,
            'energyCost' => (float) $energyCost,
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
                $this->redirect('machines/create');
            }

            $name = Validator::requiredString($_POST, 'name', 1, 120);
            $power = Validator::requiredDecimal($_POST, 'power_kw', 0);
            if ($name === null || $power === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('machines/create');
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO machines (name, power_kw, is_active, created_at, updated_at)
                 VALUES (?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $stmt->execute([$name, $power]);

            Flash::set('success', 'Utilaj adaugat.');
            $this->redirect('machines/index');
        }

        $this->render('machines/form', [
            'title' => 'Adauga utilaj',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'machine' => null,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('machines/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('machines/edit', ['id' => $id]);
            }

            $name = Validator::requiredString($_POST, 'name', 1, 120);
            $power = Validator::requiredDecimal($_POST, 'power_kw', 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === null || $power === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('machines/edit', ['id' => $id]);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE machines SET name = ?, power_kw = ?, is_active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
            );
            $stmt->execute([$name, $power, $isActive, $id]);

            Flash::set('success', 'Utilaj actualizat.');
            $this->redirect('machines/edit', ['id' => $id]);
        }

        $stmt = $this->pdo->prepare("SELECT id, name, power_kw, is_active FROM machines WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $machine = $stmt->fetch();
        if (!$machine) {
            $this->redirect('machines/index');
        }

        $this->render('machines/form', [
            'title' => 'Editeaza utilaj',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'machine' => $machine,
        ]);
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

