<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class ProductsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $q = isset($_GET['q']) && is_string($_GET['q']) ? trim((string) $_GET['q']) : '';

        $sql =
            "SELECT p.id, p.name, p.sku, pc.name AS category_name, p.sale_price, p.estimated_hours, p.manpower_hours, p.status, p.stock_qty
             FROM products p
             LEFT JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.is_active = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (p.name LIKE :q OR p.sku LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        $this->render('products/index', [
            'title' => 'Produse',
            'products' => $products,
            'q' => $q,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('products/create');
            }

            $name = Validator::requiredString($_POST, 'name', 1, 160);
            $sku = Validator::requiredString($_POST, 'sku', 1, 60);
            $categoryId = Validator::requiredInt($_POST, 'category_id', 1);
            $salePrice = Validator::requiredDecimal($_POST, 'sale_price', 0);
            $salePriceCurrency = Validator::optionalString($_POST, 'sale_price_currency', 8);
            $estimated = Validator::requiredDecimal($_POST, 'estimated_hours', 0);
            $manpower = Validator::requiredDecimal($_POST, 'manpower_hours', 0);

            if ($name === null || $sku === null || $salePrice === null || $estimated === null || $manpower === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('products/create');
            }

            if ($salePriceCurrency === null || $salePriceCurrency === '' || !in_array($salePriceCurrency, ['lei', 'usd', 'eur'], true)) {
                $salePriceCurrency = null;
            }
            $salePrice = $this->moneyToLei($salePrice, $salePriceCurrency, 4);

            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO products (name, sku, category_id, sale_price, estimated_hours, manpower_hours, status, stock_qty, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'In productie', 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                );
                $stmt->execute([$name, $sku, $categoryId, $salePrice, $estimated, $manpower]);
            } catch (\Throwable $e) {
                Flash::set('error', 'Nu se poate salva (SKU deja folosit?).');
                $this->redirect('products/create');
            }

            Flash::set('success', 'Produs creat.');
            $this->redirect('products/index');
        }

        $cats = $this->pdo->query("SELECT id, name FROM product_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        $this->render('products/form', [
            'title' => 'Adauga produs',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'categories' => $cats,
            'product' => null,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('products/index');
        }

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            $this->redirect('products/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('products/edit', ['id' => $id]);
            }

            $name = Validator::requiredString($_POST, 'name', 1, 160);
            $sku = Validator::requiredString($_POST, 'sku', 1, 60);
            $categoryId = Validator::requiredInt($_POST, 'category_id', 1);
            $salePrice = Validator::requiredDecimal($_POST, 'sale_price', 0);
            $salePriceCurrency = Validator::optionalString($_POST, 'sale_price_currency', 8);
            $estimated = Validator::requiredDecimal($_POST, 'estimated_hours', 0);
            $manpower = Validator::requiredDecimal($_POST, 'manpower_hours', 0);
            $status = Validator::requiredString($_POST, 'status', 1, 20);

            if ($name === null || $sku === null || $salePrice === null || $estimated === null || $manpower === null || $status === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('products/edit', ['id' => $id]);
            }
            if (!in_array($status, ['In productie', 'Finalizat', 'Vandut'], true)) {
                Flash::set('error', 'Status invalid.');
                $this->redirect('products/edit', ['id' => $id]);
            }

            if ($salePriceCurrency === null || $salePriceCurrency === '' || !in_array($salePriceCurrency, ['lei', 'usd', 'eur'], true)) {
                $salePriceCurrency = null;
            }
            $salePrice = $this->moneyToLei($salePrice, $salePriceCurrency, 4);

            try {
                $upd = $this->pdo->prepare(
                    "UPDATE products
                     SET name = ?, sku = ?, category_id = ?, sale_price = ?, estimated_hours = ?, manpower_hours = ?, status = ?, updated_at = UTC_TIMESTAMP()
                     WHERE id = ?"
                );
                $upd->execute([$name, $sku, $categoryId, $salePrice, $estimated, $manpower, $status, $id]);
            } catch (\Throwable $e) {
                Flash::set('error', 'Nu se poate salva (SKU deja folosit?).');
                $this->redirect('products/edit', ['id' => $id]);
            }

            Flash::set('success', 'Produs actualizat.');
            $this->redirect('products/edit', ['id' => $id]);
        }

        $cats = $this->pdo->query("SELECT id, name FROM product_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        $this->render('products/form', [
            'title' => 'Editeaza produs',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'categories' => $cats,
            'product' => $product,
        ]);
    }

    public function sell(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin', 'Operator']);

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('products/sell');
            }

            $productId = Validator::requiredInt($_POST, 'product_id', 1);
            $qty = Validator::requiredInt($_POST, 'qty', 1, 1000000);
            $salePrice = Validator::requiredDecimal($_POST, 'sale_price', 0);
            $salePriceCurrency = Validator::optionalString($_POST, 'sale_price_currency', 8);
            $customer = Validator::optionalString($_POST, 'customer_name', 160);
            $channel = Validator::optionalString($_POST, 'channel', 80);

            if ($productId === null || $qty === null || $salePrice === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('products/sell');
            }

            if ($salePriceCurrency === null || $salePriceCurrency === '' || !in_array($salePriceCurrency, ['lei', 'usd', 'eur'], true)) {
                $salePriceCurrency = null;
            }
            $salePrice = $this->moneyToLei($salePrice, $salePriceCurrency, 4);

            $this->pdo->beginTransaction();
            try {
                $p = $this->pdo->prepare("SELECT id, stock_qty FROM products WHERE id = ? FOR UPDATE");
                $p->execute([$productId]);
                $product = $p->fetch();
                if (!$product) {
                    throw new \RuntimeException('Produs lipsa');
                }

                $stock = (int) $product['stock_qty'];
                if ($stock < $qty) {
                    throw new \RuntimeException('Stoc insuficient');
                }

                $this->pdo->prepare(
                    "INSERT INTO sales (product_id, qty, sale_price, sold_at, customer_name, channel, user_id, created_at)
                     VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?, UTC_TIMESTAMP())"
                )->execute([$productId, $qty, $salePrice, $customer, $channel, (int) (Auth::user()['id'] ?? 0)]);

                $next = $stock - $qty;
                $status = $next === 0 ? 'Vandut' : 'Finalizat';
                $this->pdo->prepare("UPDATE products SET stock_qty = ?, status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")
                    ->execute([$next, $status, $productId]);

                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                Flash::set('error', $e->getMessage() === 'Stoc insuficient' ? 'Stoc insuficient.' : 'Nu se poate salva vanzarea.');
                $this->redirect('products/sell');
            }

            Flash::set('success', 'Vanzare salvata.');
            $this->redirect('products/index');
        }

        $products = $this->pdo->query(
            "SELECT id, name, sku, stock_qty, sale_price
             FROM products
             WHERE is_active = 1
             ORDER BY name ASC"
        )->fetchAll();

        $this->render('products/sell', [
            'title' => 'Vanzare',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'products' => $products,
        ]);
    }
}

