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

        View::render('layout', [
            'title' => $data['title'] ?? null,
            'content' => $content,
            'flash' => Flash::getAll(),
        ]);
    }
}
