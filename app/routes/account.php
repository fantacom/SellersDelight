<?php
declare(strict_types=1);

const SECTION_KEYS = [
    'master' => ['name', 'legal_form', 'street', 'zip', 'city', 'country', 'founded_year', 'employees', 'website', 'email', 'phone'],
    'description' => ['short_description', 'description'],
    'products' => ['products'],
];

function editor_sections(array $a): array
{
    return [
        'master' => (bool) $a['can_edit_master'],
        'description' => (bool) $a['can_edit_description'],
        'products' => (bool) $a['can_edit_products'],
        'categories' => false,
    ];
}

/** Lädt Zuweisung + Anbieter oder bricht mit 403 ab. */
function load_assignment(array $user, $id): array
{
    $a = Users::assignment((int) $user['id'], (int) $id);
    $p = $a ? Providers::byId($a['provider_id']) : null;
    if (!$p) {
        render_error(403, 'Kein Zugriff', 'Sie haben keine Berechtigung, diesen Anbieter zu bearbeiten.');
    }
    return [$a, $p];
}

/** Zeigt dem Bearbeiter seinen offenen Vorschlag statt des Live-Stands, damit nichts verloren geht. */
function with_pending(array $provider, ?array $pending): array
{
    if ($pending) {
        $proposed = form_to_provider($pending['data']);
        foreach (SECTION_KEYS as $section => $keys) {
            if (!empty($pending['sections'][$section])) {
                foreach ($keys as $k) {
                    $provider[$k] = $proposed[$k] ?? '';
                }
            }
        }
    }
    return $provider;
}

return function (Router $r) {
    $r->get('/konto', function () {
        $u = require_login();
        $assignments = array_map(fn($a) => $a + ['pending_change' => (bool) Changes::pendingFor((int) $a['provider_id'], (int) $u['id'])],
            Users::assignments((int) $u['id']));
        render('account/index', ['title' => 'Mein Konto', 'assignments' => $assignments]);
    });

    $r->get('/konto/anbieter/{id}', function ($id) {
        $u = require_login();
        [$a, $p] = load_assignment($u, $id);
        $pending = Changes::pendingFor((int) $p['id'], (int) $u['id']);
        render('account/provider-edit', [
            'title' => $p['name'],
            'provider' => with_pending($p, $pending),
            'sections' => editor_sections($a),
            'pending' => $pending,
            'lastDecision' => $pending ? null : Changes::lastDecidedFor((int) $p['id'], (int) $u['id']),
            'errors' => [],
        ]);
    });

    $r->post('/konto/anbieter/{id}', function ($id) {
        $u = require_login();
        [$a, $p] = load_assignment($u, $id);
        $sections = editor_sections($a);
        if ($sections['master'] && str(input('name')) === '') {
            render('account/provider-edit', [
                'title' => $p['name'],
                'provider' => array_merge($p, form_to_provider($_POST), ['categories' => $p['categories']]),
                'sections' => $sections, 'pending' => null, 'lastDecision' => null,
                'errors' => ['Bitte geben Sie den Firmennamen an.'],
            ], 422);
        }
        $data = $_POST;
        unset($data['_csrf']);
        Changes::submit((int) $p['id'], (int) $u['id'], $data, $sections);
        notify_admin("Änderung an „{$p['name']}“ wartet auf Freigabe", "Eingereicht von {$u['name']} <{$u['email']}>\n\nPrüfen: " . base_url() . '/admin/aenderungen');
        flash('success', 'Vielen Dank! Ihre Änderungen wurden zur Prüfung eingereicht und sind nach der Freigabe online.');
        redirect('/konto/anbieter/' . $p['id']);
    });

    $r->get('/konto/passwort', function () {
        require_login();
        render('account/password', ['title' => 'Passwort ändern', 'error' => null]);
    });

    $r->post('/konto/passwort', function () {
        $u = require_login();
        $fail = fn($e) => render('account/password', ['title' => 'Passwort ändern', 'error' => $e], 422);
        $pw = (string) input('password');
        if (!password_verify((string) input('current'), (string) $u['password_hash'])) $fail('Das aktuelle Passwort ist falsch.');
        if (mb_strlen($pw) < MIN_PASSWORD) $fail('Das neue Passwort muss mindestens ' . MIN_PASSWORD . ' Zeichen lang sein.');
        if ($pw !== input('password_confirm')) $fail('Die Passwörter stimmen nicht überein.');
        Users::setPassword((int) $u['id'], $pw);
        flash('success', 'Passwort geändert.');
        redirect(home_for($u));
    });
};
