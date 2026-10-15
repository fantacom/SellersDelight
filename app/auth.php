<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $loaded = false, $user = null;
    if (!$loaded) {
        $loaded = true;
        if (!empty($_SESSION['user_id'])) {
            $u = Users::byId($_SESSION['user_id']);
            if ($u && $u['active']) {
                $user = $u;
            } else {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    Users::touchLogin((int) $user['id']);
}

function home_for(array $user): string
{
    return $user['role'] === 'admin' ? '/admin' : '/konto';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('/login');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        render_error(403, 'Kein Zugriff', 'Dieser Bereich ist Administratoren vorbehalten.');
    }
    return $u;
}

const MIN_PASSWORD = 10;
