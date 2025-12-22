<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class UsersController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $users = $this->pdo->query(
            "SELECT id, name, username, role, is_active, last_login_at, created_at
             FROM users
             ORDER BY is_active DESC, role ASC, name ASC"
        )->fetchAll();

        $this->render('users/index', [
            'title' => 'Utilizatori',
            'users' => $users,
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
                $this->redirect('users/create');
            }

            $name = Validator::requiredString($_POST, 'name', 2, 120);
            $username = Validator::requiredString($_POST, 'username', 3, 60);
            $role = Validator::requiredString($_POST, 'role', 3, 20);
            $password = Validator::requiredString($_POST, 'password', (int) $this->config['security']['password_min_length'], 255);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === null || $username === null || $role === null || $password === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('users/create');
            }
            if (!in_array($role, ['SuperAdmin', 'Admin', 'Operator'], true)) {
                Flash::set('error', 'Rol invalid.');
                $this->redirect('users/create');
            }
            if ($role === 'SuperAdmin' && (Auth::user()['role'] ?? '') !== 'SuperAdmin') {
                Flash::set('error', 'Doar SuperAdmin poate crea alt SuperAdmin.');
                $this->redirect('users/create');
            }
            if (!Validator::passwordComplex($password, (int) $this->config['security']['password_min_length'])) {
                Flash::set('error', 'Parola trebuie sa fie complexa (minim 12, litere mari/mici, cifra, simbol).');
                $this->redirect('users/create');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO users (name, username, password_hash, role, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                );
                $stmt->execute([$name, $username, $hash, $role, $isActive]);
            } catch (\Throwable $e) {
                Flash::set('error', 'Nu se poate salva (username deja folosit?).');
                $this->redirect('users/create');
            }

            Flash::set('success', 'Utilizator creat.');
            $this->redirect('users/index');
        }

        $this->render('users/form', [
            'title' => 'Adauga utilizator',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'userRow' => null,
            'password_min_length' => (int) $this->config['security']['password_min_length'],
            'currentRole' => (string) (Auth::user()['role'] ?? ''),
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        Auth::requireRole(['SuperAdmin', 'Admin']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            $this->redirect('users/index');
        }

        $stmt = $this->pdo->prepare("SELECT id, name, username, role, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $userRow = $stmt->fetch();
        if (!$userRow) {
            $this->redirect('users/index');
        }

        $currentUserRole = (string) (Auth::user()['role'] ?? '');
        if ((string) $userRow['role'] === 'SuperAdmin' && $currentUserRole !== 'SuperAdmin') {
            http_response_code(403);
            echo 'Acces interzis.';
            return;
        }

        $csrfKey = $this->config['security']['csrf_key'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('users/edit', ['id' => $id]);
            }

            $name = Validator::requiredString($_POST, 'name', 2, 120);
            $username = Validator::requiredString($_POST, 'username', 3, 60);
            $role = Validator::requiredString($_POST, 'role', 3, 20);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $password = Validator::optionalString($_POST, 'password', 255);

            if ($name === null || $username === null || $role === null) {
                Flash::set('error', 'Campuri invalide.');
                $this->redirect('users/edit', ['id' => $id]);
            }
            if (!in_array($role, ['SuperAdmin', 'Admin', 'Operator'], true)) {
                Flash::set('error', 'Rol invalid.');
                $this->redirect('users/edit', ['id' => $id]);
            }
            if ($role === 'SuperAdmin' && $currentUserRole !== 'SuperAdmin') {
                Flash::set('error', 'Doar SuperAdmin poate seta rolul SuperAdmin.');
                $this->redirect('users/edit', ['id' => $id]);
            }

            if ($id === (int) (Auth::user()['id'] ?? 0)) {
                $isActive = 1;
            }

            $this->pdo->beginTransaction();
            try {
                $upd = $this->pdo->prepare("UPDATE users SET name = ?, username = ?, role = ?, is_active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
                $upd->execute([$name, $username, $role, $isActive, $id]);

                if ($password !== null && $password !== '') {
                    if (!Validator::passwordComplex($password, (int) $this->config['security']['password_min_length'])) {
                        throw new \RuntimeException('Parola slaba');
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $this->pdo->prepare("UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$hash, $id]);
                }

                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                Flash::set('error', $e->getMessage() === 'Parola slaba' ? 'Parola trebuie sa fie complexa.' : 'Nu se poate salva.');
                $this->redirect('users/edit', ['id' => $id]);
            }

            Flash::set('success', 'Utilizator actualizat.');
            $this->redirect('users/edit', ['id' => $id]);
        }

        $this->render('users/form', [
            'title' => 'Editeaza utilizator',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'userRow' => $userRow,
            'password_min_length' => (int) $this->config['security']['password_min_length'],
            'currentRole' => $currentUserRole,
        ]);
    }
}

