<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Controller
{
    protected array $config;
    protected PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    protected function redirect(string $route, array $params = []): void
    {
        $url = $this->url($route, $params);
        header('Location: ' . $url);
        exit;
    }

    protected function url(string $route, array $params = []): string
    {
        $qs = array_merge(['r' => $route], $params);
        return '/?' . http_build_query($qs);
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->redirect('auth/login');
        }
    }

    protected function render(string $template, array $data = []): void
    {
        $lang = $this->getSettingValue('language', 'ro');
        if (!in_array($lang, ['ro', 'en'], true)) {
            $lang = 'ro';
        }

        $currency = $this->getSettingValue('currency', 'lei');
        if (!in_array($currency, ['lei', 'usd', 'eur'], true)) {
            $currency = 'lei';
        }
        $currencyLabel = match ($currency) {
            'usd' => 'USD',
            'eur' => 'EUR',
            default => 'lei',
        };
        $fx = $this->fxRates();
        $rate = match ($currency) {
            'usd' => (float) ($fx['usd'] ?? 0),
            'eur' => (float) ($fx['eur'] ?? 0),
            default => 1.0,
        };
        if ($currency !== 'lei' && $rate <= 0) {
            $rate = 1.0;
        }

        $toCurrency = static fn ($lei): float => $rate > 0 ? ((float) $lei / $rate) : (float) $lei;
        $fromCurrency = static fn ($amount): float => (float) $amount * $rate;
        $money = static fn ($lei, int $decimals = 2): string => number_format($toCurrency($lei), $decimals) . ' ' . $currencyLabel;

        $fxRates = [
            'lei' => 1.0,
            'usd' => (float) ($fx['usd'] ?? 0),
            'eur' => (float) ($fx['eur'] ?? 0),
        ];
        if ($fxRates['usd'] <= 0) {
            $fxRates['usd'] = 1.0;
        }
        if ($fxRates['eur'] <= 0) {
            $fxRates['eur'] = 1.0;
        }

        $globals = [
            'lang' => $lang,
            'currency' => $currency,
            'currency_label' => $currencyLabel,
            'money' => $money,
            'to_currency' => $toCurrency,
            'from_currency' => $fromCurrency,
            'fx_rate' => $rate,
            'fx_date' => (string) ($fx['date'] ?? ''),
            'fx_rates' => $fxRates,
        ];

        ob_start();
        View::render($template, array_merge($globals, $data));
        $content = (string) ob_get_clean();

        $appVersion = isset($this->config['app']['version']) ? (string) $this->config['app']['version'] : '';
        $git = $this->gitMeta();

        View::render('layout', array_merge($globals, [
            'title' => $data['title'] ?? null,
            'content' => $content,
            'flash' => Flash::getAll(),
            'app_version' => $appVersion,
            'git_hash' => $git['hash'] ?? null,
        ]));
    }

    private function gitMeta(): array
    {
        $root = realpath(__DIR__ . '/../../');
        if (!is_string($root) || $root === '') {
            return [];
        }

        $head = $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'HEAD';
        if (!is_file($head)) {
            return [];
        }

        $content = trim((string) file_get_contents($head));
        if ($content === '') {
            return [];
        }

        $hash = '';
        if (str_starts_with($content, 'ref:')) {
            $ref = trim(substr($content, 4));
            $refPath = $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ref);
            if (is_file($refPath)) {
                $hash = trim((string) file_get_contents($refPath));
            }
        } elseif (preg_match('/^[0-9a-f]{40}$/i', $content)) {
            $hash = $content;
        }

        if ($hash === '' || !preg_match('/^[0-9a-f]{40}$/i', $hash)) {
            return [];
        }

        return ['hash' => substr($hash, 0, 7)];
    }

    private function getSettingValue(string $key, string $fallback): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? (string) $row['value'] : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    protected function currentCurrency(): string
    {
        $currency = $this->getSettingValue('currency', 'lei');
        return in_array($currency, ['lei', 'usd', 'eur'], true) ? $currency : 'lei';
    }

    protected function moneyToLei(string $amount, ?string $currency = null, int $decimals = 4): string
    {
        $cur = $currency ?? $this->currentCurrency();
        $rate = $this->fxRateRon($cur);
        $lei = (float) $amount * $rate;
        return number_format($lei, $decimals, '.', '');
    }

    protected function leiToMoney(string $amountLei, ?string $currency = null, int $decimals = 4): string
    {
        $cur = $currency ?? $this->currentCurrency();
        $rate = $this->fxRateRon($cur);
        $value = $rate > 0 ? ((float) $amountLei / $rate) : (float) $amountLei;
        return number_format($value, $decimals, '.', '');
    }

    protected function fxRateRon(string $currency): float
    {
        if ($currency === 'lei') {
            return 1.0;
        }
        $fx = $this->fxRates();
        $rate = (float) ($fx[$currency] ?? 0);
        return $rate > 0 ? $rate : 1.0;
    }

    private function fxRates(): array
    {
        $today = date('Y-m-d');

        $cachedDate = $this->getSettingValue('fx_date', '');
        $cachedEur = $this->parseFloat($this->getSettingValue('fx_eur', ''));
        $cachedUsd = $this->parseFloat($this->getSettingValue('fx_usd', ''));
        if ($cachedDate === $today && $cachedEur > 0 && $cachedUsd > 0) {
            return ['date' => $cachedDate, 'eur' => $cachedEur, 'usd' => $cachedUsd];
        }

        $raw = $this->downloadUrl('https://www.bnr.ro/nbrfxrates.xml');
        if ($raw === null) {
            if ($cachedEur > 0 && $cachedUsd > 0) {
                return ['date' => $cachedDate !== '' ? $cachedDate : $today, 'eur' => $cachedEur, 'usd' => $cachedUsd];
            }
            return ['date' => $today, 'eur' => 1.0, 'usd' => 1.0];
        }

        $xml = @simplexml_load_string($raw);
        if (!$xml) {
            if ($cachedEur > 0 && $cachedUsd > 0) {
                return ['date' => $cachedDate !== '' ? $cachedDate : $today, 'eur' => $cachedEur, 'usd' => $cachedUsd];
            }
            return ['date' => $today, 'eur' => 1.0, 'usd' => 1.0];
        }

        $cube = $xml->Body->Cube ?? null;
        if (!$cube) {
            if ($cachedEur > 0 && $cachedUsd > 0) {
                return ['date' => $cachedDate !== '' ? $cachedDate : $today, 'eur' => $cachedEur, 'usd' => $cachedUsd];
            }
            return ['date' => $today, 'eur' => 1.0, 'usd' => 1.0];
        }

        $dateAttr = isset($cube['date']) ? (string) $cube['date'] : '';
        $date = $this->normalizeDate($dateAttr, $today);

        $eur = 0.0;
        $usd = 0.0;
        foreach ($cube->Rate as $r) {
            $cur = isset($r['currency']) ? strtolower((string) $r['currency']) : '';
            if ($cur !== 'eur' && $cur !== 'usd') {
                continue;
            }
            $mult = isset($r['multiplier']) ? (int) $r['multiplier'] : 1;
            if ($mult < 1) {
                $mult = 1;
            }
            $val = $this->parseFloat((string) $r);
            if ($val <= 0) {
                continue;
            }
            $perUnit = $val / $mult;
            if ($cur === 'eur') {
                $eur = $perUnit;
            } else {
                $usd = $perUnit;
            }
        }

        if ($eur > 0 && $usd > 0) {
            $this->setSettingValue('fx_date', $today);
            $this->setSettingValue('fx_eur', $this->formatFloat($eur, 6));
            $this->setSettingValue('fx_usd', $this->formatFloat($usd, 6));
            return ['date' => $today, 'eur' => $eur, 'usd' => $usd];
        }

        if ($cachedEur > 0 && $cachedUsd > 0) {
            return ['date' => $cachedDate !== '' ? $cachedDate : $today, 'eur' => $cachedEur, 'usd' => $cachedUsd];
        }
        return ['date' => $today, 'eur' => 1.0, 'usd' => 1.0];
    }

    private function normalizeDate(string $raw, string $fallback): string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $raw)) {
            [$d, $m, $y] = explode('.', $raw);
            return $y . '-' . $m . '-' . $d;
        }
        return $fallback;
    }

    private function parseFloat(string $v): float
    {
        $v = trim(str_replace(',', '.', $v));
        if (!preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return 0.0;
        }
        return (float) $v;
    }

    private function formatFloat(float $v, int $decimals): string
    {
        return number_format($v, $decimals, '.', '');
    }

    private function setSettingValue(string $key, string $value): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute([$key, $value]);
        } catch (\Throwable $e) {
        }
    }

    private function downloadUrl(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'WSM-FX');
            $out = curl_exec($ch);
            $ch = null;
            return is_string($out) && $out !== '' ? $out : null;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: WSM-FX\r\n",
            ],
            'https' => [
                'timeout' => 10,
            ],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        return is_string($out) && $out !== '' ? $out : null;
    }
}
