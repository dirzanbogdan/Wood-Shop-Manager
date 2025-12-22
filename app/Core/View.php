<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $path = __DIR__ . '/../Views/' . $template . '.php';
        if (!is_file($path)) {
            http_response_code(500);
            echo 'Template lipsa.';
            exit;
        }

        extract($data, EXTR_SKIP);
        require $path;
    }
}

