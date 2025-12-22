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
        ob_start();
        View::render($template, $data);
        $content = (string) ob_get_clean();

        $appVersion = isset($this->config['app']['version']) ? (string) $this->config['app']['version'] : '';
        $git = $this->gitMeta();

        View::render('layout', [
            'title' => $data['title'] ?? null,
            'content' => $content,
            'flash' => Flash::getAll(),
            'app_version' => $appVersion,
            'git_hash' => $git['hash'] ?? null,
        ]);
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
}
