<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;

final class UpdateController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin']);

        $csrfKey = $this->config['security']['csrf_key'];
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('update/index');
            }

            if ($action === 'backup_download') {
                $this->dumpDatabaseToDownload();
                return;
            }

            if ($action === 'backup_server') {
                $path = $this->dumpDatabaseToServer();
                Flash::set('success', 'Backup creat: ' . $path);
                $this->redirect('update/index');
            }

            if ($action === 'pull_update') {
                $res = $this->runGitUpdate();
                Flash::set($res['ok'] ? 'success' : 'error', $res['message']);
                $this->redirect('update/index');
            }

            if ($action === 'apply_update') {
                $res = $this->runZipUpdate();
                Flash::set($res['ok'] ? 'success' : 'error', $res['message']);
                $this->redirect('update/index');
            }
        }

        $currentVersion = isset($this->config['app']['version']) ? (string) $this->config['app']['version'] : '';
        $git = $this->gitInfo();
        $changelog = $this->loadChangelog();

        $this->render('update/index', [
            'title' => 'Update',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'current_version' => $currentVersion,
            'git_info' => $git,
            'changelog' => $changelog,
        ]);
    }

    private function dumpDatabaseToDownload(): void
    {
        $filename = 'wsm_backup_' . gmdate('Ymd_His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb');
        $this->writeSqlDump($out);
        fclose($out);
        exit;
    }

    private function dumpDatabaseToServer(): string
    {
        $root = $this->projectRoot();
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Nu pot crea directorul de backup.');
        }

        $filename = 'wsm_backup_' . gmdate('Ymd_His') . '.sql';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Nu pot scrie fisierul de backup.');
        }

        $this->writeSqlDump($fh);
        fclose($fh);

        return 'storage/backups/' . $filename;
    }

    private function writeSqlDump($out): void
    {
        fwrite($out, "SET NAMES utf8mb4;\n");
        fwrite($out, "SET time_zone = '+00:00';\n");
        fwrite($out, "SET foreign_key_checks = 0;\n\n");

        $tables = $this->pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll();
        foreach ($tables as $row) {
            $table = (string) array_values($row)[0];

            $stmt = $this->pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table) . "`");
            $createRow = $stmt->fetch();
            $createSql = is_array($createRow) && isset($createRow['Create Table']) ? (string) $createRow['Create Table'] : '';
            if ($createSql === '') {
                continue;
            }

            fwrite($out, "\nDROP TABLE IF EXISTS `" . $table . "`;\n");
            fwrite($out, $createSql . ";\n\n");

            $count = (int) $this->pdo->query("SELECT COUNT(*) AS c FROM `" . str_replace('`', '``', $table) . "`")->fetch()['c'];
            if ($count < 1) {
                continue;
            }

            $batchSize = 500;
            for ($offset = 0; $offset < $count; $offset += $batchSize) {
                $rows = $this->pdo->query(
                    "SELECT * FROM `" . str_replace('`', '``', $table) . "` LIMIT " . (int) $batchSize . " OFFSET " . (int) $offset
                )->fetchAll();
                if (!$rows) {
                    break;
                }

                $cols = array_keys($rows[0]);
                $colSql = implode(', ', array_map(static fn ($c) => '`' . str_replace('`', '``', (string) $c) . '`', $cols));
                $valuesSql = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($cols as $c) {
                        $v = $r[$c];
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } elseif (is_int($v) || is_float($v)) {
                            $vals[] = (string) $v;
                        } else {
                            $vals[] = $this->pdo->quote((string) $v);
                        }
                    }
                    $valuesSql[] = '(' . implode(', ', $vals) . ')';
                }

                fwrite($out, "INSERT INTO `" . $table . "` (" . $colSql . ") VALUES\n" . implode(",\n", $valuesSql) . ";\n");
            }
        }

        fwrite($out, "\nSET foreign_key_checks = 1;\n");
    }

    private function projectRoot(): string
    {
        $root = realpath(__DIR__ . '/../../');
        if (!is_string($root) || $root === '') {
            throw new \RuntimeException('Root invalid.');
        }
        return $root;
    }

    private function gitInfo(): array
    {
        $root = $this->projectRoot();
        $head = $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'HEAD';
        if (!is_file($head)) {
            return ['available' => false];
        }

        $headValue = trim((string) file_get_contents($head));
        $hash = '';
        $branch = '';
        if (str_starts_with($headValue, 'ref:')) {
            $ref = trim(substr($headValue, 4));
            $branch = basename(str_replace('\\', '/', $ref));
            $refPath = $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ref);
            if (is_file($refPath)) {
                $hash = trim((string) file_get_contents($refPath));
            }
        } elseif (preg_match('/^[0-9a-f]{40}$/i', $headValue)) {
            $hash = $headValue;
        }

        $short = (preg_match('/^[0-9a-f]{40}$/i', $hash) ? substr($hash, 0, 7) : '');

        $canShell = function_exists('proc_open');
        return [
            'available' => true,
            'branch' => $branch,
            'hash' => $short,
            'can_shell' => $canShell,
        ];
    }

    private function runGitUpdate(): array
    {
        $root = $this->projectRoot();
        if (!is_dir($root . DIRECTORY_SEPARATOR . '.git')) {
            return ['ok' => false, 'message' => 'Proiectul nu pare clonat cu git pe server.'];
        }
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'message' => 'proc_open este dezactivat pe server.'];
        }

        $env = array_merge($_ENV, [
            'GIT_TERMINAL_PROMPT' => '0',
            'LANG' => 'C',
        ]);

        $cmd = 'git pull --ff-only';
        $spec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $spec, $pipes, $root, $env);
        if (!is_resource($proc)) {
            return ['ok' => false, 'message' => 'Nu pot porni git.'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($proc);
        $out = trim((string) $stdout);
        $err = trim((string) $stderr);
        $msg = $out !== '' ? $out : ($err !== '' ? $err : 'Fara output.');

        return ['ok' => $code === 0, 'message' => $msg];
    }

    private function runZipUpdate(): array
    {
        $root = $this->projectRoot();
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'message' => 'ZipArchive nu este disponibil pe server.'];
        }

        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates' . DIRECTORY_SEPARATOR . gmdate('Ymd_His');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'Nu pot crea directorul de update.'];
        }

        $zipPath = $dir . DIRECTORY_SEPARATOR . 'update.zip';
        $extractDir = $dir . DIRECTORY_SEPARATOR . 'extract';

        $url = 'https://github.com/dirzanbogdan/Wood-Shop-Manager/archive/refs/heads/main.zip';
        $dl = $this->downloadFile($url, $zipPath);
        if (!$dl['ok']) {
            $this->deleteTree($dir);
            return $dl;
        }

        $unzip = $this->extractZip($zipPath, $extractDir);
        if (!$unzip['ok']) {
            $this->deleteTree($dir);
            return $unzip;
        }

        $srcRoot = $this->singleDirectoryInside($extractDir);
        if ($srcRoot === null) {
            $this->deleteTree($dir);
            return ['ok' => false, 'message' => 'Arhiva update este invalida.'];
        }

        $exclude = [
            'config/local.php',
            'storage',
            '.git',
        ];
        $copyRes = $this->copyTree($srcRoot, $root, $exclude);
        if (!$copyRes['ok']) {
            $this->deleteTree($dir);
            return $copyRes;
        }

        $dbRes = $this->applyDatabaseUpdates();
        $this->deleteTree($dir);
        if (!$dbRes['ok']) {
            return $dbRes;
        }

        return ['ok' => true, 'message' => 'Update aplicat din arhiva GitHub + DB actualizat.'];
    }

    private function downloadFile(string $url, string $dest): array
    {
        if (function_exists('curl_init')) {
            $fh = fopen($dest, 'wb');
            if ($fh === false) {
                return ['ok' => false, 'message' => 'Nu pot scrie fisierul de update.'];
            }
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_FILE, $fh);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            $ok = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $ch = null;
            fclose($fh);
            if ($ok === false) {
                @unlink($dest);
                $msg = $err !== '' ? $err : ('Eroare download (HTTP ' . $code . ').');
                return ['ok' => false, 'message' => 'Nu pot descarca update: ' . $msg];
            }
            return ['ok' => true, 'message' => 'OK'];
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => 1,
                'header' => "User-Agent: WSM-Updater\r\n",
            ],
            'https' => [
                'timeout' => 60,
            ],
        ]);

        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return ['ok' => false, 'message' => 'Nu pot descarca update (allow_url_fopen/curl).'];
        }
        if (file_put_contents($dest, $data) === false) {
            return ['ok' => false, 'message' => 'Nu pot salva arhiva update.'];
        }
        return ['ok' => true, 'message' => 'OK'];
    }

    private function extractZip(string $zipPath, string $extractDir): array
    {
        $zip = new \ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            return ['ok' => false, 'message' => 'Nu pot deschide arhiva update.'];
        }
        if (!is_dir($extractDir) && !mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
            $zip->close();
            return ['ok' => false, 'message' => 'Nu pot crea directorul de extract.'];
        }
        $ok = $zip->extractTo($extractDir);
        $zip->close();
        return $ok ? ['ok' => true, 'message' => 'OK'] : ['ok' => false, 'message' => 'Nu pot extrage arhiva update.'];
    }

    private function singleDirectoryInside(string $dir): ?string
    {
        $items = array_values(array_filter((array) scandir($dir), static fn ($p): bool => $p !== '.' && $p !== '..'));
        if (!$items) {
            return null;
        }
        $candidates = [];
        foreach ($items as $i) {
            $p = $dir . DIRECTORY_SEPARATOR . $i;
            if (is_dir($p)) {
                $candidates[] = $p;
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        return null;
    }

    private function copyTree(string $srcRoot, string $destRoot, array $excludeRelPaths): array
    {
        $srcRoot = rtrim($srcRoot, "\\/");
        $destRoot = rtrim($destRoot, "\\/");

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($rii as $item) {
            $srcPath = (string) $item->getPathname();
            $rel = ltrim(str_replace(['\\', '/'], '/', substr($srcPath, strlen($srcRoot))), '/');
            if ($rel === '') {
                continue;
            }

            $skip = false;
            foreach ($excludeRelPaths as $ex) {
                $ex = trim(str_replace(['\\', '/'], '/', $ex), '/');
                if ($ex === '') {
                    continue;
                }
                if ($rel === $ex || str_starts_with($rel, $ex . '/')) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $destPath = $destRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $destDir = is_dir($srcPath) ? $destPath : dirname($destPath);
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return ['ok' => false, 'message' => 'Nu pot crea director: ' . $rel];
            }

            if (is_file($srcPath)) {
                if (!@copy($srcPath, $destPath)) {
                    return ['ok' => false, 'message' => 'Nu pot copia fisier: ' . $rel];
                }
            }
        }

        return ['ok' => true, 'message' => 'OK'];
    }

    private function deleteTree(string $path): void
    {
        $this->deleteTreeInner($path);
    }

    private function deleteTreeInner(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $this->deleteTreeInner($path . DIRECTORY_SEPARATOR . $i);
        }
        @rmdir($path);
    }

    private function applyDatabaseUpdates(): array
    {
        $root = $this->projectRoot();
        $schemaFile = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (is_file($schemaFile)) {
            $sql = (string) file_get_contents($schemaFile);
            $statements = preg_split("/;\s*\n/", $sql) ?: [];
            $this->pdo->beginTransaction();
            try {
                foreach ($statements as $stmt) {
                    $stmt = trim((string) $stmt);
                    if ($stmt === '' || strncmp($stmt, '--', 2) === 0) {
                        continue;
                    }
                    $this->pdo->exec($stmt);
                }

                $this->ensureSettingsKey('timezone', 'Europe/Bucharest');
                $this->ensureSettingsKey('language', 'ro');
                $this->ensureSettingsKey('currency', 'lei');
                $this->ensureColumn('materials', 'purchase_url', "ALTER TABLE materials ADD COLUMN purchase_url VARCHAR(500) NULL");

                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                return ['ok' => false, 'message' => 'Update DB esuat: ' . $e->getMessage()];
            }
        }
        return ['ok' => true, 'message' => 'OK'];
    }

    private function ensureSettingsKey(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            $ins = $this->pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
            $ins->execute([$key, $value]);
        }
    }

    private function ensureColumn(string $table, string $column, string $alterSql): void
    {
        $t = $this->pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $t->execute([$table]);
        $tableExists = (int) (($t->fetch()['c'] ?? 0)) > 0;
        if (!$tableExists) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        $exists = (int) (($stmt->fetch()['c'] ?? 0)) > 0;
        if (!$exists) {
            $this->pdo->exec($alterSql);
        }
    }

    private function loadChangelog(): array
    {
        $root = $this->projectRoot();
        $path = $root . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
        if (!is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];

        $versions = [];
        $current = '';
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                $current = trim((string) $m[1]);
                if ($current !== '' && !isset($versions[$current])) {
                    $versions[$current] = [];
                }
                continue;
            }
            if ($current === '') {
                continue;
            }
            if (preg_match('/^-+\s+(.*)$/', $line, $m)) {
                $versions[$current][] = trim((string) $m[1]);
            }
        }

        $out = [];
        foreach ($versions as $v => $items) {
            $out[] = ['version' => $v, 'items' => $items];
        }
        return $out;
    }
}
