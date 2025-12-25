<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Router
{
    public static function dispatch(array $config, PDO $pdo): void
    {
        $route = isset($_GET['r']) && is_string($_GET['r']) ? trim($_GET['r']) : '';
        if ($route === '') {
            $route = Auth::check() ? 'dashboard/index' : 'auth/login';
        }

        [$controllerName, $action] = array_pad(explode('/', $route, 2), 2, '');
        $controllerName = strtolower($controllerName);
        $action = $action !== '' ? $action : 'index';

        $map = [
            'auth' => \App\Controllers\AuthController::class,
            'dashboard' => \App\Controllers\DashboardController::class,
            'materials' => \App\Controllers\MaterialsController::class,
            'machines' => \App\Controllers\MachinesController::class,
            'products' => \App\Controllers\ProductsController::class,
            'bom' => \App\Controllers\BomController::class,
            'production' => \App\Controllers\ProductionController::class,
            'reports' => \App\Controllers\ReportsController::class,
            'settings' => \App\Controllers\SettingsController::class,
            'update' => \App\Controllers\UpdateController::class,
            'users' => \App\Controllers\UsersController::class,
            'suppliers' => \App\Controllers\SuppliersController::class,
            'materialtypes' => \App\Controllers\MaterialTypesController::class,
            'categories' => \App\Controllers\ProductCategoriesController::class,
            'api' => \App\Controllers\ApiController::class,
        ];

        if (!isset($map[$controllerName])) {
            http_response_code(404);
            echo 'Pagina inexistenta.';
            return;
        }

        $controllerClass = $map[$controllerName];
        $controller = new $controllerClass($config, $pdo);
        if (!method_exists($controller, $action)) {
            http_response_code(404);
            echo 'Actiune inexistenta.';
            return;
        }

        $controller->{$action}();
    }
}
