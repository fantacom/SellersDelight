<?php
declare(strict_types=1);

// Ersteinrichtung: Solange kein Benutzer existiert, wird hier der erste Administrator angelegt.
return function (Router $r) {
    $r->get('/einrichtung', function () {
        if (Users::count() > 0) {
            redirect('/login');
        }
        render('setup/index', ['title' => 'Einrichtung', 'errors' => [], 'form' => []]);
    });

    $r->post('/einrichtung', function () {
        if (Users::count() > 0) {
            redirect('/login');
        }
        $form = ['name' => str(input('name'), 100), 'email' => mb_strtolower(str(input('email'), 150))];
        $pw = (string) input('password');
        $errors = [];
        if ($form['name'] === '') $errors[] = 'Bitte geben Sie Ihren Namen an.';
        if (!is_email($form['email'])) $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
        if (mb_strlen($pw) < MIN_PASSWORD) $errors[] = 'Das Passwort muss mindestens ' . MIN_PASSWORD . ' Zeichen lang sein.';
        if ($pw !== input('password_confirm')) $errors[] = 'Die Passwörter stimmen nicht überein.';
        if ($errors) {
            render('setup/index', ['title' => 'Einrichtung', 'errors' => $errors, 'form' => $form], 422);
        }
        $id = Users::create($form['email'], $form['name'], 'admin');
        Users::setPassword($id, $pw);
        login_user(Users::byId($id));
        flash('success', "Willkommen! Seller's Delight ist eingerichtet.");
        redirect('/admin');
    });
};
