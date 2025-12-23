<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $csrfKey = $this->config['security']['csrf_key'];
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('settings/index');
            }

            if ($action === '' || $action === 'settings_save') {
                $energy = Validator::requiredDecimal($_POST, 'energy_cost_per_kwh', 0);
                $hourly = Validator::requiredDecimal($_POST, 'operator_hourly_cost', 0);
                $timezone = Validator::optionalString($_POST, 'timezone', 64);
                if ($energy === null || $hourly === null) {
                    Flash::set('error', 'Valori invalide.');
                    $this->redirect('settings/index');
                }
                if ($timezone === null || $timezone === '') {
                    $timezone = 'Europe/Bucharest';
                }
                if (!in_array($timezone, timezone_identifiers_list(), true)) {
                    Flash::set('error', 'Timezone invalid.');
                    $this->redirect('settings/index');
                }

                $this->setSetting('energy_cost_per_kwh', $energy);
                $this->setSetting('operator_hourly_cost', $hourly);
                $this->setSetting('timezone', $timezone);

                Flash::set('success', 'Setari salvate.');
                $this->redirect('settings/index');
            }

            if ($action === 'unit_create') {
                $code = Validator::requiredString($_POST, 'code', 1, 20);
                $name = Validator::requiredString($_POST, 'name', 1, 60);
                if ($code === null || $name === null) {
                    Flash::set('error', 'Campuri invalide.');
                    $this->redirect('settings/index');
                }

                try {
                    $stmt = $this->pdo->prepare("INSERT INTO units (code, name, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
                    $stmt->execute([$code, $name]);
                    Flash::set('success', 'Unitate adaugata.');
                } catch (\Throwable $e) {
                    Flash::set('error', 'Nu se poate adauga (cod duplicat?).');
                }
                $this->redirect('settings/index');
            }

            if ($action === 'unit_update') {
                $id = Validator::requiredInt($_POST, 'unit_id', 1);
                $code = Validator::requiredString($_POST, 'code', 1, 20);
                $name = Validator::requiredString($_POST, 'name', 1, 60);
                if ($id === null || $code === null || $name === null) {
                    Flash::set('error', 'Campuri invalide.');
                    $this->redirect('settings/index');
                }

                try {
                    $stmt = $this->pdo->prepare("UPDATE units SET code = ?, name = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
                    $stmt->execute([$code, $name, $id]);
                    Flash::set('success', 'Unitate actualizata.');
                } catch (\Throwable $e) {
                    Flash::set('error', 'Nu se poate actualiza (cod duplicat?).');
                }
                $this->redirect('settings/index');
            }

            if ($action === 'unit_delete') {
                $id = Validator::requiredInt($_POST, 'unit_id', 1);
                if ($id === null) {
                    Flash::set('error', 'Cerere invalida.');
                    $this->redirect('settings/index');
                }

                $m = $this->pdo->prepare("SELECT COUNT(*) AS c FROM materials WHERE unit_id = ?");
                $m->execute([$id]);
                $materialsCnt = (int) ($m->fetch()['c'] ?? 0);

                $bm = $this->pdo->prepare("SELECT COUNT(*) AS c FROM bom_materials WHERE unit_id = ?");
                $bm->execute([$id]);
                $bomCnt = (int) ($bm->fetch()['c'] ?? 0);

                if ($materialsCnt > 0 || $bomCnt > 0) {
                    Flash::set('error', 'Unitate folosita in sistem (materiale/BOM). Nu se poate sterge.');
                    $this->redirect('settings/index');
                }

                try {
                    $del = $this->pdo->prepare("DELETE FROM units WHERE id = ? LIMIT 1");
                    $del->execute([$id]);
                    Flash::set('success', 'Unitate stearsa.');
                } catch (\Throwable $e) {
                    Flash::set('error', 'Nu se poate sterge unitatea.');
                }
                $this->redirect('settings/index');
            }
        }

        $units = $this->pdo->query("SELECT id, code, name FROM units ORDER BY code ASC")->fetchAll();

        $this->render('settings/index', [
            'title' => 'Setari',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'energy_cost_per_kwh' => $this->getSetting('energy_cost_per_kwh', '1.00'),
            'operator_hourly_cost' => $this->getSetting('operator_hourly_cost', '0.00'),
            'timezone' => $this->getSetting('timezone', 'Europe/Bucharest'),
            'units' => $units,
        ]);
    }

    public function getSetting(string $key, string $fallback): string
    {
        $stmt = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : $fallback;
    }

    private function setSetting(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([$key, $value]);
    }
}

