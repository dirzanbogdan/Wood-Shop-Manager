<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;

final class UpdateController extends Controller
{
    private function apkFsPath(string $root): string
    {
        return $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'wsm.apk';
    }

    private function apkBackupPath(string $root): ?string
    {
        $tmpDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        $backupDir = is_dir($tmpDir) ? $tmpDir : rtrim((string) sys_get_temp_dir(), "\\/");
        if ($backupDir === '') {
            return null;
        }
        return $backupDir . DIRECTORY_SEPARATOR . 'wsm.apk.backup';
    }

    private function apkLooksValid(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        clearstatcache(true, $path);
        $size = filesize($path);
        return $size !== false && (int) $size >= (1024 * 1024);
    }

    private function backupApkIfPresent(string $root): array
    {
        $apkFsPath = $this->apkFsPath($root);
        $backupPath = $this->apkBackupPath($root);
        if ($backupPath === null) {
            return ['ok' => false, 'path' => null, 'reason' => 'no_tmp'];
        }

        if ($this->apkLooksValid($apkFsPath)) {
            if (@copy($apkFsPath, $backupPath)) {
                return ['ok' => true, 'path' => $backupPath, 'reason' => 'fresh_copy'];
            }
            if ($this->apkLooksValid($backupPath)) {
                return ['ok' => true, 'path' => $backupPath, 'reason' => 'existing_backup'];
            }
            return ['ok' => false, 'path' => null, 'reason' => 'copy_failed'];
        }

        if ($this->apkLooksValid($backupPath)) {
            return ['ok' => true, 'path' => $backupPath, 'reason' => 'existing_backup'];
        }

        return ['ok' => false, 'path' => null, 'reason' => is_file($apkFsPath) ? 'too_small' : 'missing'];
    }

    private function restoreApkIfNeeded(string $root, array $backup, string $msg): string
    {
        $backupPath = isset($backup['path']) && is_string($backup['path']) ? $backup['path'] : null;
        $backupOk = ($backup['ok'] ?? false) === true && $backupPath !== null && $backupPath !== '';
        if (!$backupOk) {
            return $msg;
        }
        if (!$this->apkLooksValid($backupPath)) {
            return $msg;
        }

        $apkFsPath = $this->apkFsPath($root);
        $apkDir = dirname($apkFsPath);
        if (!is_dir($apkDir)) {
            @mkdir($apkDir, 0755, true);
        }
        clearstatcache(true, $apkFsPath);
        $apkSizeNow = is_file($apkFsPath) ? filesize($apkFsPath) : false;
        $needsRestore = ($apkSizeNow === false) || ((int) $apkSizeNow < (1024 * 1024));
        if (!$needsRestore) {
            return $msg;
        }

        if (@copy($backupPath, $apkFsPath)) {
            clearstatcache(true, $apkFsPath);
            return trim($msg . "\n\nAPK: pastrat local (restore dupa update).");
        }

        return trim($msg . "\n\nATENTIE: nu pot restaura APK-ul real dupa update (permisiuni / path).");
    }

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
        $updateBranch = $this->updateGitBranch();

        $this->render('update/index', [
            'title' => 'Update',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'current_version' => $currentVersion,
            'git_info' => $git,
            'changelog' => $changelog,
            'update_git_branch' => $updateBranch,
        ]);
    }

    private function updateGitBranch(): string
    {
        $branch = '';
        if (isset($this->config['update']) && is_array($this->config['update']) && isset($this->config['update']['git_branch'])) {
            $branch = trim((string) $this->config['update']['git_branch']);
        }
        if ($branch === '') {
            $branch = 'main';
        }
        if (!preg_match('/^[A-Za-z0-9._\\/-]{1,120}$/', $branch)) {
            $branch = 'main';
        }
        return $branch;
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

    private function gitBin(): string
    {
        $candidates = [
            '/usr/bin/git',
            '/usr/local/bin/git',
            '/bin/git',
            '/usr/local/git/bin/git',
        ];
        foreach ($candidates as $p) {
            if (is_file($p) && is_executable($p)) {
                return $p;
            }
        }
        return 'git';
    }

    private function gitEnv(string $root): array
    {
        $path = (string) getenv('PATH');
        if (trim($path) === '') {
            $path = '/usr/local/bin:/usr/bin:/bin';
        }

        $cacheDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $tmpDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        return array_merge($_ENV, [
            'PATH' => $path,
            'HOME' => $tmpDir,
            'GIT_TERMINAL_PROMPT' => '0',
            'LANG' => 'C',
            'GIT_CONFIG_GLOBAL' => $cacheDir . DIRECTORY_SEPARATOR . 'gitconfig',
        ]);
    }

    private function runGit(array $args, string $cwd, array $env): array
    {
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'code' => 127, 'out' => '', 'err' => 'proc_open este dezactivat pe server.'];
        }

        $bin = $this->gitBin();
        $cmd = $bin . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $spec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $spec, $pipes, $cwd, $env);
        if (!is_resource($proc)) {
            $last = error_get_last();
            $msg = isset($last['message']) && is_string($last['message']) ? $last['message'] : 'Nu pot porni git.';
            return ['ok' => false, 'code' => 127, 'out' => '', 'err' => $msg];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return [
            'ok' => $code === 0,
            'code' => $code,
            'out' => trim((string) $stdout),
            'err' => trim((string) $stderr),
        ];
    }

    private function gitInfo(): array
    {
        $root = $this->projectRoot();
        $head = $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'HEAD';
        if (!is_file($head)) {
            return ['available' => false];
        }

        $canShell = function_exists('proc_open');
        if ($canShell) {
            $branch = '';
            $short = '';
            $env = $this->gitEnv($root);

            $r1 = $this->runGit(['rev-parse', '--abbrev-ref', 'HEAD'], $root, $env);
            if (($r1['out'] ?? '') !== '' && (string) ($r1['out'] ?? '') !== 'HEAD') {
                $branch = (string) $r1['out'];
            }

            $r2 = $this->runGit(['rev-parse', '--short', 'HEAD'], $root, $env);
            if (preg_match('/^[0-9a-f]{7,40}$/i', (string) ($r2['out'] ?? ''))) {
                $short = substr((string) $r2['out'], 0, 7);
            }

            return [
                'available' => true,
                'branch' => $branch,
                'hash' => $short,
                'can_shell' => true,
            ];
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
        return [
            'available' => true,
            'branch' => $branch,
            'hash' => $short,
            'can_shell' => false,
        ];
    }

    private function runGitUpdate(): array
    {
        $root = $this->projectRoot();
        if (!is_dir($root . DIRECTORY_SEPARATOR . '.git')) {
            return ['ok' => false, 'message' => 'Proiectul nu pare clonat cu git pe server.'];
        }
        $env = $this->gitEnv($root);
        $allowedBranch = $this->updateGitBranch();

        $apkBackup = $this->backupApkIfPresent($root);

        $logs = [];
        $run = function (array $args, string $label) use ($root, $env, &$logs): array {
            $res = $this->runGit($args, $root, $env);
            $code = (string) ($res['code'] ?? '');
            $out = (string) ($res['out'] ?? '');
            $err = (string) ($res['err'] ?? '');
            $logs[] = '=== ' . $label . ' ==='
                . "\nexit=" . $code
                . "\nOUT:\n" . ($out !== '' ? $out : '-')
                . "\nERR:\n" . ($err !== '' ? $err : '-');
            return $res;
        };

        $trackedApk = $run(['ls-files', '--', 'public/downloads/wsm.apk'], 'git ls-files -- public/downloads/wsm.apk');
        if (trim((string) ($trackedApk['out'] ?? '')) !== '') {
            $run(['update-index', '--skip-worktree', 'public/downloads/wsm.apk'], 'git update-index --skip-worktree public/downloads/wsm.apk');
        }

        $branchRes = $run(['rev-parse', '--abbrev-ref', 'HEAD'], 'git rev-parse --abbrev-ref HEAD');
        $currentBranch = trim((string) ($branchRes['out'] ?? ''));
        if ($currentBranch === '' || $currentBranch === 'HEAD') {
            return [
                'ok' => false,
                'message' => 'Update blocat: repository in stare "detached HEAD". Comuta pe branch ' . $allowedBranch . ' si reincearca.',
            ];
        }
        if ($currentBranch !== $allowedBranch) {
            return [
                'ok' => false,
                'message' => 'Update blocat: branch curent `' . $currentBranch . '`. Permis doar `' . $allowedBranch . '` pentru `git pull` din UI.',
            ];
        }

        $beforeHead = $run(['rev-parse', '--short', 'HEAD'], 'git rev-parse --short HEAD (before)');
        $beforeHeadShort = '';
        if (preg_match('/^[0-9a-f]{7,40}$/i', (string) ($beforeHead['out'] ?? ''))) {
            $beforeHeadShort = substr((string) $beforeHead['out'], 0, 7);
        }

        $pull = $run(['pull', '--ff-only', 'origin', $allowedBranch], 'git pull --ff-only origin ' . $allowedBranch);
        $out = (string) ($pull['out'] ?? '');
        $err = (string) ($pull['err'] ?? '');
        $msg = trim($out !== '' ? $out : $err);
        if ($msg === '') {
            $msg = 'Fara output.';
        }

        $isOkByOutput = false;
        $combined = trim($out . "\n" . $err);
        if ($combined !== '') {
            $isOkByOutput = (bool) preg_match('/\b(Already up[ -]to[ -]date\.|Fast-forward|Updating\s+[0-9a-f]{7,40}\.\.[0-9a-f]{7,40})\b/i', $combined);
        }

        $hasHardFailure = $combined !== '' && (bool) preg_match('/\b(fatal:|Aborting|Permission denied|Cannot update the ref)\b/i', $combined);
        $ok = ((($pull['code'] ?? 1) === 0) || $isOkByOutput) && !$hasHardFailure;
        if (!$ok && preg_match('/dubious ownership|safe\.directory/i', $combined)) {
            $run(['config', '--global', '--add', 'safe.directory', $root], 'git config --global --add safe.directory');
            $pull2 = $run(['pull', '--ff-only', 'origin', $allowedBranch], 'git pull --ff-only origin ' . $allowedBranch . ' (after safe.directory)');
            $out = (string) ($pull2['out'] ?? '');
            $err = (string) ($pull2['err'] ?? '');
            $combined = trim($out . "\n" . $err);
            $msg = trim($out !== '' ? $out : $err);
            if ($msg === '') {
                $msg = 'Fara output.';
            }
            $isOkByOutput = $combined !== '' && (bool) preg_match('/\b(Already up[ -]to[ -]date\.|Fast-forward|Updating\s+[0-9a-f]{7,40}\.\.[0-9a-f]{7,40})\b/i', $combined);
            $hasHardFailure = $combined !== '' && (bool) preg_match('/\b(fatal:|Aborting|Permission denied|Cannot update the ref)\b/i', $combined);
            $ok = ((($pull2['code'] ?? 1) === 0) || $isOkByOutput) && !$hasHardFailure;
        }

        if (
            !$ok
            && preg_match('/Unable to append to \.git\/logs|Cannot update the ref|Permission denied/i', $combined)
        ) {
            $cfg = $run(['config', '--local', 'core.logAllRefUpdates', 'false'], 'git config --local core.logAllRefUpdates false');
            if (($cfg['code'] ?? 1) === 0) {
                $pullFix = $run(['pull', '--ff-only', 'origin', $allowedBranch], 'git pull --ff-only origin ' . $allowedBranch . ' (after reflog off)');
                $out = (string) ($pullFix['out'] ?? '');
                $err = (string) ($pullFix['err'] ?? '');
                $combined = trim($out . "\n" . $err);
                $msg = trim($out !== '' ? $out : $err);
                if ($msg === '') {
                    $msg = 'Fara output.';
                }
                $isOkByOutput = $combined !== '' && (bool) preg_match('/\b(Already up[ -]to[ -]date\.|Fast-forward|Updating\s+[0-9a-f]{7,40}\.\.[0-9a-f]{7,40})\b/i', $combined);
                $hasHardFailure = $combined !== '' && (bool) preg_match('/\b(fatal:|Aborting|Permission denied|Cannot update the ref)\b/i', $combined);
                $ok = ((($pullFix['code'] ?? 1) === 0) || $isOkByOutput) && !$hasHardFailure;
            }
        }

        if (
            !$ok
            && preg_match('/Your local changes to the following files would be overwritten by merge/i', $combined)
        ) {
            $reset = $run(['reset', '--hard', 'HEAD'], 'git reset --hard HEAD (auto)');
            $pull3 = $run(['pull', '--ff-only', 'origin', $allowedBranch], 'git pull --ff-only origin ' . $allowedBranch . ' (after reset --hard)');
            $out = (string) ($pull3['out'] ?? '');
            $err = (string) ($pull3['err'] ?? '');
            $combined = trim($out . "\n" . $err);
            $msg = trim($out !== '' ? $out : $err);
            if ($msg === '') {
                $msg = 'Fara output.';
            }
            $ok = (($pull3['code'] ?? 1) === 0) || ($combined !== '' && (bool) preg_match('/\b(Already up[ -]to[ -]date\.|Fast-forward|Updating\s+[0-9a-f]{7,40}\.\.[0-9a-f]{7,40})\b/i', $combined));
            if (($reset['code'] ?? 1) === 0) {
                $msg = trim('Reset local (discard changes): OK' . "\n" . $msg);
            } else {
                $msg = trim('Reset local (discard changes): ESUAT' . "\n" . $msg);
            }
        }

        if (!$ok && preg_match('/not found|No such file or directory/i', $combined) && str_contains($combined, 'git')) {
            $msg = $msg . "\n" . 'Sugestie: pe server, git nu este in PATH pentru PHP. Foloseste update din arhiva GitHub (zip) sau seteaza PATH/HOME pentru PHP.';
        }

        if ($ok) {
            clearstatcache(true);
            $msg = $this->restoreApkIfNeeded($root, $apkBackup, $msg);

            $apkFsPath = $this->apkFsPath($root);
            clearstatcache(true, $apkFsPath);
            $apkSize = is_file($apkFsPath) ? filesize($apkFsPath) : false;
            if ($apkSize !== false && (int) $apkSize < (1024 * 1024)) {
                $msg = trim($msg . "\n\nATENTIE: `public/downloads/wsm.apk` pare invalid (fisier prea mic). Incarca manual APK-ul in `public/downloads/wsm.apk`.");
            }
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            $head = $run(['rev-parse', '--short', 'HEAD'], 'git rev-parse --short HEAD (after)');
            $headOut = (string) ($head['out'] ?? '');
            if (preg_match('/^[0-9a-f]{7,40}$/i', $headOut)) {
                $afterHeadShort = substr($headOut, 0, 7);
                if ($beforeHeadShort !== '' && $afterHeadShort !== '' && $beforeHeadShort !== $afterHeadShort) {
                    $msg = trim($msg . "\nHEAD: " . $beforeHeadShort . ' -> ' . $afterHeadShort);
                } else {
                    $msg = trim($msg . "\nHEAD: " . $afterHeadShort);
                }
            }

            $status = $run(['status', '--porcelain', '--untracked-files=no'], 'git status --porcelain --untracked-files=no');
            $porcelain = trim((string) ($status['out'] ?? ''));
            if ($porcelain !== '') {
                $run(['reset', '--hard', 'HEAD'], 'git reset --hard HEAD (cleanup)');
                $status2 = $run(['status', '--porcelain', '--untracked-files=no'], 'git status --porcelain --untracked-files=no (after cleanup)');
                $porcelain2 = trim((string) ($status2['out'] ?? ''));
                if ($porcelain2 !== '') {
                    $msg = trim($msg . "\nATENTIE: exista modificari locale in repo (git status --porcelain):\n" . $porcelain2);
                }
            }
        }

        if ($logs) {
            $msg = trim($msg . "\n\n---\nLOGS:\n" . implode("\n\n", $logs));
        }

        if (!$ok && preg_match('/Unable to append to \.git\/logs|Cannot update the ref|Permission denied/i', $combined)) {
            $msg = trim($msg . "\n\nSugestie: userul PHP nu are permisiuni de scriere in folderul `.git` (in special `.git/logs` si `.git/refs`). Ruleaza update din arhiva GitHub (zip) sau acorda permisiuni corecte pentru userul web.");
        }

        return ['ok' => $ok, 'message' => $msg];
    }

    private function runZipUpdate(): array
    {
        $root = $this->projectRoot();
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'message' => 'ZipArchive nu este disponibil pe server.'];
        }

        $apkBackup = $this->backupApkIfPresent($root);

        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates' . DIRECTORY_SEPARATOR . gmdate('Ymd_His');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'Nu pot crea directorul de update.'];
        }

        $zipPath = $dir . DIRECTORY_SEPARATOR . 'update.zip';
        $extractDir = $dir . DIRECTORY_SEPARATOR . 'extract';

        $branch = $this->updateGitBranch();
        $url = 'https://github.com/dirzanbogdan/Wood-Shop-Manager/archive/refs/heads/' . rawurlencode($branch) . '.zip';
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
            'public/downloads',
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

        $msg = 'Update aplicat din arhiva GitHub + DB actualizat.';
        $msg = $this->restoreApkIfNeeded($root, $apkBackup, $msg);

        $apkFsPath = $this->apkFsPath($root);
        clearstatcache(true, $apkFsPath);
        $apkSize = is_file($apkFsPath) ? filesize($apkFsPath) : false;
        if ($apkSize !== false && (int) $apkSize < (1024 * 1024)) {
            $msg = trim($msg . "\n\nATENTIE: `public/downloads/wsm.apk` pare invalid (fisier prea mic). Incarca manual APK-ul in `public/downloads/wsm.apk`.");
        }

        return ['ok' => true, 'message' => $msg];
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
                $this->ensureColumn('materials', 'product_code', "ALTER TABLE materials ADD COLUMN product_code VARCHAR(80) NULL AFTER name");
                $this->ensureMaterialsProductCodeUniqueIndex();

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

    private function ensureMaterialsProductCodeUniqueIndex(): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materials' AND COLUMN_NAME = 'product_code'"
        );
        $stmt->execute();
        $colExists = (int) (($stmt->fetch()['c'] ?? 0)) > 0;
        if (!$colExists) {
            return;
        }

        $idx = $this->pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materials' AND INDEX_NAME = 'materials_product_code_unique'"
        );
        $idx->execute();
        $idxExists = (int) (($idx->fetch()['c'] ?? 0)) > 0;
        if ($idxExists) {
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

        $this->pdo->exec("ALTER TABLE materials ADD UNIQUE KEY materials_product_code_unique (product_code)");
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
