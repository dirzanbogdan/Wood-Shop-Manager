<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Flash;
use App\Core\Validator;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard/index');
        }

        $csrfKey = $this->config['security']['csrf_key'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::verify($csrfKey, $_POST[$csrfKey] ?? null)) {
                Flash::set('error', 'Sesiune invalida. Reincarca pagina.');
                $this->redirect('auth/login');
            }

            $username = Validator::requiredString($_POST, 'username', 1, 60);
            $password = Validator::requiredString($_POST, 'password', $this->config['security']['password_min_length'], 255);
            if ($username === null || $password === null) {
                Flash::set('error', 'Date de autentificare invalide.');
                $this->redirect('auth/login');
            }

            if (!Auth::attempt($this->pdo, $username, $password)) {
                Flash::set('error', 'Username sau parola incorecte.');
                $this->redirect('auth/login');
            }

            $this->redirect('dashboard/index');
        }

        $this->render('auth/login', [
            'title' => 'Login',
            'csrf' => CSRF::token($csrfKey),
            'csrf_key' => $csrfKey,
            'password_min_length' => (int) $this->config['security']['password_min_length'],
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('auth/login');
    }
}
