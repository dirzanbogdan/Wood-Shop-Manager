<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class MaterialTypesController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $rows = $this->pdo->query("SELECT id, name, is_active FROM material_types ORDER BY is_active DESC, name ASC")->fetchAll();

        $this->render('materialtypes/index', [
            'title' => 'Tipuri materiale',
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
                $this->redirect('materialtypes/create');
            }

            $name = Validator::requiredString($_POST, 'name', 2, 80);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($name === null) {
                Flash::set('error', 'Nume invalid.');
                $this->redirect('materialtypes/create');
            }

            try {
                $this->pdo->prepare(
                    "INSERT INTO material_types (name, is_active, created_at, updated_at)
                     VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                )->execute([$name, $isActive]);
            } catch (\Throwable $e) {
                Flash::set('error', 'Nu se poate salva (nume deja folosit?).');
                $this->redirect('materialtypes/create');
            }

            Flash::set('success', 'Tip material adaugat.');
            $this->redirect('materialtypes/index');
        }

        $this->render('materialtypes/form', [
            'title' => 'Adauga tip material',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'row' => null,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('materialtypes/index');
        }

        $stmt = $this->pdo->prepare("SELECT id, name, is_active FROM material_types WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->redirect('materialtypes/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('materialtypes/edit', ['id' => $id]);
            }

            $name = Validator::requiredString($_POST, 'name', 2, 80);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($name === null) {
                Flash::set('error', 'Nume invalid.');
                $this->redirect('materialtypes/edit', ['id' => $id]);
            }

            try {
                $this->pdo->prepare(
                    "UPDATE material_types SET name = ?, is_active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
                )->execute([$name, $isActive, $id]);
            } catch (\Throwable $e) {
                Flash::set('error', 'Nu se poate salva (nume deja folosit?).');
                $this->redirect('materialtypes/edit', ['id' => $id]);
            }

            Flash::set('success', 'Tip material actualizat.');
            $this->redirect('materialtypes/edit', ['id' => $id]);
        }

        $this->render('materialtypes/form', [
            'title' => 'Editeaza tip material',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'row' => $row,
        ]);
    }
}

