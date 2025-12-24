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
                $language = Validator::optionalString($_POST, 'language', 8);
                $currency = Validator::optionalString($_POST, 'currency', 8);
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
                if ($language === null || $language === '' || !in_array($language, ['ro', 'en'], true)) {
                    $language = 'ro';
                }
                if ($currency === null || $currency === '' || !in_array($currency, ['lei', 'usd', 'eur'], true)) {
                    $currency = 'lei';
                }

                $energyCur = Validator::optionalString($_POST, 'energy_cost_per_kwh_currency', 8);
                $hourlyCur = Validator::optionalString($_POST, 'operator_hourly_cost_currency', 8);
                if ($energyCur === null || $energyCur === '' || !in_array($energyCur, ['lei', 'usd', 'eur'], true)) {
                    $energyCur = $currency;
                }
                if ($hourlyCur === null || $hourlyCur === '' || !in_array($hourlyCur, ['lei', 'usd', 'eur'], true)) {
                    $hourlyCur = $currency;
                }

                $energyLei = $this->moneyToLei($energy, $energyCur, 4);
                $hourlyLei = $this->moneyToLei($hourly, $hourlyCur, 4);

                $this->setSetting('energy_cost_per_kwh', $energyLei);
                $this->setSetting('operator_hourly_cost', $hourlyLei);
                $this->setSetting('timezone', $timezone);
                $this->setSetting('language', $language);
                $this->setSetting('currency', $currency);

                Flash::set('success', 'Setari salvate.');
                $this->redirect('settings/index');
            }

            if ($action === 'taxes_save') {
                $entityType = Validator::optionalString($_POST, 'entity_type', 16);
                if (!in_array($entityType, ['srl', 'other'], true)) {
                    $entityType = 'srl';
                }

                $taxType = Validator::optionalString($_POST, 'tax_type', 32);
                $taxValue = '';

                if ($entityType === 'srl') {
                    if (!in_array($taxType, ['income_1', 'income_3', 'profit_16'], true)) {
                        $taxType = 'income_1';
                    }
                } else {
                    if (!in_array($taxType, ['income', 'profit'], true)) {
                        $taxType = 'income';
                    }
                    $taxValueRaw = Validator::requiredDecimal($_POST, 'tax_value', 0);
                    if ($taxValueRaw === null || (float) $taxValueRaw < 0 || (float) $taxValueRaw > 100) {
                        Flash::set('error', 'Valoare impozit invalida.');
                        $this->redirect('settings/index');
                    }
                    $taxValue = $taxValueRaw;
                }

                $this->setSetting('entity_type', $entityType);
                $this->setSetting('tax_type', (string) $taxType);
                $this->setSetting('tax_value', $taxValue);

                Flash::set('success', 'Taxe salvate.');
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

        $cur = $this->currentCurrency();
        $energy = $this->leiToMoney($this->getSetting('energy_cost_per_kwh', '1.00'), $cur, 4);
        $hourly = $this->leiToMoney($this->getSetting('operator_hourly_cost', '0.00'), $cur, 4);
        $entityType = $this->getSetting('entity_type', 'srl');
        if (!in_array($entityType, ['srl', 'other'], true)) {
            $entityType = 'srl';
        }
        $taxType = $this->getSetting('tax_type', $entityType === 'srl' ? 'income_1' : 'income');
        $taxValue = $this->getSetting('tax_value', '0');

        $this->render('settings/index', [
            'title' => 'Setari',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'energy_cost_per_kwh' => $energy,
            'operator_hourly_cost' => $hourly,
            'timezone' => $this->getSetting('timezone', 'Europe/Bucharest'),
            'language' => $this->getSetting('language', 'ro'),
            'currency' => $this->getSetting('currency', 'lei'),
            'entity_type' => $entityType,
            'tax_type' => $taxType,
            'tax_value' => $taxValue,
            'timezones' => timezone_identifiers_list(),
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
