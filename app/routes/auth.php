<?php
declare(strict_types=1);

return function (Router $r) {
    $r->get('/login', function () {
        if ($u = current_user()) {
            redirect(home_for($u));
        }
        render('auth/login', ['title' => 'Anmelden', 'email' => '', 'error' => null]);
    });

    $r->post('/login', function () {
        $email = mb_strtolower(str(input('email'), 150));
        $fail = fn($error) => render('auth/login', ['title' => 'Anmelden', 'email' => $email, 'error' => $error], 401);
        if (!rate_limit('login:' . client_ip(), 900, 10)) {
            $fail('Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten.');
        }
        $user = Users::byEmail($email);
        if (!$user || !$user['active'] || !$user['password_hash'] || !password_verify((string) input('password'), $user['password_hash'])) {
            $fail('E-Mail-Adresse oder Passwort ist falsch.');
        }
        $returnTo = $_SESSION['return_to'] ?? '';
        unset($_SESSION['return_to']);
        login_user($user);
        redirect(str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : home_for($user));
    });

    $r->post('/logout', function () {
        $_SESSION = [];
        session_destroy();
        redirect('/');
    });

    $r->get('/einladung/{token}', function ($t) {
        render('auth/invite', ['title' => 'Zugang einrichten', 'invited' => Users::byInviteToken($t), 'error' => null]);
    });

    $r->post('/einladung/{token}', function ($t) {
        $invited = Users::byInviteToken($t);
        if (!$invited) {
            render('auth/invite', ['title' => 'Zugang einrichten', 'invited' => null, 'error' => null]);
        }
        $pw = (string) input('password');
        $error = null;
        if (mb_strlen($pw) < MIN_PASSWORD) $error = 'Das Passwort muss mindestens ' . MIN_PASSWORD . ' Zeichen lang sein.';
        elseif ($pw !== input('password_confirm')) $error = 'Die Passwörter stimmen nicht überein.';
        if ($error) {
            render('auth/invite', ['title' => 'Zugang einrichten', 'invited' => $invited, 'error' => $error], 422);
        }
        Users::setPassword((int) $invited['id'], $pw);
        login_user($invited);
        flash('success', "Ihr Zugang ist eingerichtet. Willkommen bei Seller's Delight!");
        redirect(home_for($invited));
    });
};
