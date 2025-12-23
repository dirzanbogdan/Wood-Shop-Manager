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
