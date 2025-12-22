<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class SuppliersController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $rows = $this->pdo->query(
            "SELECT id, name, phone, email, is_active
             FROM suppliers
             ORDER BY is_active DESC, name ASC"
        )->fetchAll();

        $this->render('suppliers/index', [
            'title' => 'Furnizori',
            'rows' => $rows,
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
                $this->redirect('suppliers/create');
            }

            $name = Validator::requiredString($_POST, 'name', 2, 160);
            $phone = Validator::optionalString($_POST, 'phone', 40);
            $email = Validator::optionalString($_POST, 'email', 120);
            $notes = Validator::optionalString($_POST, 'notes', 5000);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === null) {
                Flash::set('error', 'Nume invalid.');
                $this->redirect('suppliers/create');
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO suppliers (name, phone, email, notes, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $stmt->execute([$name, $phone, $email, $notes, $isActive]);

            Flash::set('success', 'Furnizor adaugat.');
            $this->redirect('suppliers/index');
        }

        $this->render('suppliers/form', [
            'title' => 'Adauga furnizor',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'supplier' => null,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('suppliers/index');
        }

        $stmt = $this->pdo->prepare("SELECT * FROM suppliers WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        if (!$supplier) {
            $this->redirect('suppliers/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('suppliers/edit', ['id' => $id]);
            }

            $name = Validator::requiredString($_POST, 'name', 2, 160);
            $phone = Validator::optionalString($_POST, 'phone', 40);
            $email = Validator::optionalString($_POST, 'email', 120);
            $notes = Validator::optionalString($_POST, 'notes', 5000);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === null) {
                Flash::set('error', 'Nume invalid.');
                $this->redirect('suppliers/edit', ['id' => $id]);
            }

            $upd = $this->pdo->prepare(
                "UPDATE suppliers
                 SET name = ?, phone = ?, email = ?, notes = ?, is_active = ?, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?"
            );
            $upd->execute([$name, $phone, $email, $notes, $isActive, $id]);

            Flash::set('success', 'Furnizor actualizat.');
            $this->redirect('suppliers/edit', ['id' => $id]);
        }

        $this->render('suppliers/form', [
            'title' => 'Editeaza furnizor',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'supplier' => $supplier,
        ]);
    }
}

