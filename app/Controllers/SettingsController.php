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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('settings/index');
            }

            $energy = Validator::requiredDecimal($_POST, 'energy_cost_per_kwh', 0);
            $hourly = Validator::requiredDecimal($_POST, 'operator_hourly_cost', 0);
            if ($energy === null || $hourly === null) {
                Flash::set('error', 'Valori invalide.');
                $this->redirect('settings/index');
            }

            $this->setSetting('energy_cost_per_kwh', $energy);
            $this->setSetting('operator_hourly_cost', $hourly);

            Flash::set('success', 'Setari salvate.');
            $this->redirect('settings/index');
        }

        $this->render('settings/index', [
            'title' => 'Setari',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'energy_cost_per_kwh' => $this->getSetting('energy_cost_per_kwh', '1.00'),
            'operator_hourly_cost' => $this->getSetting('operator_hourly_cost', '0.00'),
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

