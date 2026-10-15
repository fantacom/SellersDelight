<?php
declare(strict_types=1);

const ALL_SECTIONS = ['master' => true, 'description' => true, 'categories' => true, 'products' => true];
const PROVIDER_STATUSES = ['pending', 'published', 'rejected'];
const FIELD_LABELS = [
    'master' => [
        'name' => 'Firmenname', 'legal_form' => 'Rechtsform', 'street' => 'Straße', 'zip' => 'PLZ', 'city' => 'Ort',
        'country' => 'Land', 'founded_year' => 'Gegründet', 'employees' => 'Mitarbeiter', 'website' => 'Website',
        'email' => 'E-Mail', 'phone' => 'Telefon',
    ],
    'description' => ['short_description' => 'Kurzbeschreibung', 'description' => 'Beschreibung'],
];

function admin_render(string $template, array $vars = [], int $status = 200): void
{
    $vars['pending'] = [
        'reviews' => Reviews::countByStatus()['pending'],
        'providers' => Providers::countByStatus()['pending'],
        'changes' => Changes::countPending(),
    ];
    render('admin/' . $template, $vars, $status);
}

function perms_from_post(): array
{
    return ['master' => input('can_edit_master') !== '', 'description' => input('can_edit_description') !== '', 'products' => input('can_edit_products') !== ''];
}

function provider_or_404($id): array
{
    return Providers::byId($id) ?? not_found();
}

/** Vergleicht den Live-Stand mit dem Vorschlag – nur Bereiche, für die der Bearbeiter Rechte hatte. */
function build_diff(array $provider, array $change): array
{
    $proposed = Providers::normalize($change['data']);
    $rows = [];
    foreach (['master', 'description'] as $section) {
        if (empty($change['sections'][$section])) continue;
        foreach (FIELD_LABELS[$section] as $key => $label) {
            $before = (string) ($provider[$key] ?? '');
            $after = (string) ($proposed[$section][$key] ?? '');
            $rows[] = ['label' => $label, 'before' => $before, 'after' => $after, 'changed' => $before !== $after];
        }
    }
    $products = null;
    if (!empty($change['sections']['products'])) {
        $fmt = fn($list) => array_map(fn($p) => implode(' · ', array_filter([$p['name'], $p['description'], $p['url'], $p['price_info']])), $list);
        $before = $fmt($provider['products']);
        $after = $fmt($proposed['products']);
        $products = ['before' => $before, 'after' => $after, 'changed' => $before !== $after];
    }
    return ['rows' => $rows, 'products' => $products];
}

return function (Router $r) {
    /* ---------- Übersicht ---------- */
    $r->get('/admin', function () {
        require_admin();
        admin_render('dashboard', [
            'title' => 'Übersicht',
            'reviewCounts' => Reviews::countByStatus(),
            'providerCounts' => Providers::countByStatus(),
            'pendingReviews' => array_slice(Reviews::list('pending'), 0, 5),
            'pendingProviders' => Providers::list(['status' => 'pending', 'sort' => 'newest', 'limit' => 5]),
            'pendingChanges' => array_slice(Changes::list('pending'), 0, 5),
            'categoryCount' => count(nav_categories()),
            'userCount' => count(Users::all()),
        ]);
    });

    /* ---------- Bewertungen ---------- */
    $r->get('/admin/bewertungen', function () {
        require_admin();
        $status = in_array(query('status'), ['pending', 'approved', 'rejected', 'all'], true) ? query('status') : 'pending';
        admin_render('reviews', [
            'title' => 'Bewertungen', 'status' => $status, 'counts' => Reviews::countByStatus(),
            'reviews' => Reviews::list($status === 'all' ? null : $status),
        ]);
    });

    $r->post('/admin/bewertungen/{id}/{action}', function ($id, $action) {
        $u = require_admin();
        $map = ['freigeben' => 'approved', 'ablehnen' => 'rejected', 'zuruecksetzen' => 'pending'];
        if ($action === 'loeschen') {
            Reviews::remove($id);
            flash('success', 'Bewertung gelöscht.');
        } elseif (isset($map[$action])) {
            Reviews::moderate($id, $map[$action], (int) $u['id']);
            flash('success', $map[$action] === 'approved' ? 'Bewertung freigegeben.' : 'Bewertung aktualisiert.');
        }
        redirect_back('/admin/bewertungen');
    });

    /* ---------- Anbieter ---------- */
    $r->get('/admin/anbieter', function () {
        require_admin();
        $status = in_array(query('status'), PROVIDER_STATUSES, true) ? query('status') : '';
        $q = str(query('q'), 100);
        admin_render('providers', [
            'title' => 'Anbieter', 'status' => $status, 'q' => $q, 'counts' => Providers::countByStatus(),
            'providers' => Providers::list(['status' => $status ?: null, 'search' => $q, 'sort' => $status === 'pending' ? 'newest' : 'name', 'limit' => 1000]),
        ]);
    });

    $r->get('/admin/anbieter/neu', function () {
        require_admin();
        admin_render('provider-edit', ['title' => 'Neuer Anbieter', 'provider' => ['category_ids' => [], 'products' => [], 'status' => 'published'], 'errors' => [], 'editors' => []]);
    });

    $r->post('/admin/anbieter/neu', function () {
        require_admin();
        if (str(input('name')) === '') {
            admin_render('provider-edit', ['title' => 'Neuer Anbieter', 'provider' => form_to_provider($_POST), 'errors' => ['Bitte geben Sie den Firmennamen an.'], 'editors' => []], 422);
        }
        $status = in_array(input('status'), PROVIDER_STATUSES, true) ? input('status') : 'published';
        $id = Providers::save($_POST, null, ALL_SECTIONS, $status);
        flash('success', 'Anbieter angelegt.');
        redirect("/admin/anbieter/$id");
    });

    $r->get('/admin/anbieter/{id}', function ($id) {
        require_admin();
        $p = provider_or_404($id);
        admin_render('provider-edit', ['title' => $p['name'], 'provider' => $p, 'errors' => [], 'editors' => Users::editorsOf((int) $p['id'])]);
    });

    $r->post('/admin/anbieter/{id}', function ($id) {
        require_admin();
        $p = provider_or_404($id);
        if (str(input('name')) === '') {
            admin_render('provider-edit', [
                'title' => $p['name'], 'provider' => array_merge(form_to_provider($_POST), ['id' => $p['id'], 'slug' => $p['slug']]),
                'errors' => ['Bitte geben Sie den Firmennamen an.'], 'editors' => Users::editorsOf((int) $p['id']),
            ], 422);
        }
        $status = in_array(input('status'), PROVIDER_STATUSES, true) ? input('status') : $p['status'];
        Providers::save($_POST, (int) $p['id'], ALL_SECTIONS, $status);
        flash('success', 'Änderungen gespeichert.');
        redirect('/admin/anbieter/' . $p['id']);
    });

    $r->post('/admin/anbieter/{id}/status', function ($id) {
        require_admin();
        if (in_array(input('status'), PROVIDER_STATUSES, true)) {
            Providers::setStatus($id, input('status'));
            flash('success', input('status') === 'published' ? 'Anbieter veröffentlicht.' : 'Status geändert.');
        }
        redirect_back('/admin/anbieter');
    });

    $r->post('/admin/anbieter/{id}/loeschen', function ($id) {
        require_admin();
        Providers::remove($id);
        flash('success', 'Anbieter gelöscht.');
        redirect('/admin/anbieter');
    });

    // Legt (falls nötig) einen Bearbeiter-Zugang an, weist den Anbieter zu und verschickt die Einladung.
    $r->post('/admin/anbieter/{id}/zugang', function ($id) {
        require_admin();
        $p = provider_or_404($id);
        $email = mb_strtolower(str(input('email'), 150));
        if (!is_email($email)) {
            flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
            redirect("/admin/anbieter/{$p['id']}#zugaenge");
        }
        $user = Users::byEmail($email) ?? Users::byId(Users::create($email, str(input('name'), 100) ?: $p['name'], 'editor'));
        Users::assign((int) $user['id'], (int) $p['id'], perms_from_post());
        if (input('send_invite') !== '' && !$user['password_hash']) {
            invite_flash(send_invite($user, $p['name']), $email);
        } else {
            flash('success', "Zugriff für $email gespeichert.");
        }
        redirect("/admin/anbieter/{$p['id']}#zugaenge");
    });

    /* ---------- Profil-Änderungen ---------- */
    $r->get('/admin/aenderungen', function () {
        require_admin();
        $status = in_array(query('status'), ['pending', 'approved', 'rejected'], true) ? query('status') : 'pending';
        admin_render('changes', ['title' => 'Änderungen', 'status' => $status, 'changes' => Changes::list($status)]);
    });

    $r->get('/admin/aenderungen/{id}', function ($id) {
        require_admin();
        $c = Changes::byId($id) ?? not_found();
        $p = provider_or_404($c['provider_id']);
        admin_render('change', ['title' => "Änderung: {$p['name']}", 'change' => $c, 'provider' => $p, 'diff' => build_diff($p, $c)]);
    });

    $r->post('/admin/aenderungen/{id}/{action}', function ($id, $action) {
        $u = require_admin();
        $c = Changes::byId($id);
        if (!$c || !in_array($action, ['freigeben', 'ablehnen'], true)) not_found();
        if ($c['status'] !== 'pending') {
            flash('error', 'Diese Änderung wurde bereits bearbeitet.');
            redirect('/admin/aenderungen');
        }
        $approve = $action === 'freigeben';
        $note = str(input('note'), 1000);
        if ($approve) {
            Providers::save($c['data'], (int) $c['provider_id'], $c['sections']);
        }
        Changes::decide((int) $c['id'], $approve ? 'approved' : 'rejected', (int) $u['id'], $note);
        if ($c['user_email']) {
            send_mail($c['user_email'],
                $approve ? "Ihre Änderungen an „{$c['provider_name']}“ sind online" : "Ihre Änderungen an „{$c['provider_name']}“",
                "Hallo {$c['user_name']},\n\n" . ($approve
                    ? "Ihre Änderungen am Profil „{$c['provider_name']}“ wurden freigegeben und sind jetzt online."
                    : "Ihre Änderungen am Profil „{$c['provider_name']}“ wurden leider nicht übernommen.")
                . ($note !== '' ? "\n\nAnmerkung: $note" : '')
                . "\n\n" . base_url() . "/konto/anbieter/{$c['provider_id']}\n\nViele Grüße\nIhr Team von Seller's Delight");
        }
        flash('success', $approve ? 'Änderung übernommen und online.' : 'Änderung abgelehnt.');
        redirect('/admin/aenderungen');
    });

    /* ---------- Kategorien ---------- */
    $r->get('/admin/kategorien', function () {
        require_admin();
        admin_render('categories', ['title' => 'Kategorien', 'categories' => nav_categories()]);
    });

    $r->post('/admin/kategorien', function () {
        require_admin();
        if (str(input('name')) === '') {
            flash('error', 'Bitte einen Namen angeben.');
        } else {
            Categories::save($_POST);
            flash('success', 'Kategorie „' . str(input('name')) . '“ angelegt.');
        }
        redirect('/admin/kategorien');
    });

    $r->post('/admin/kategorien/{id}', function ($id) {
        require_admin();
        if (str(input('name')) === '') {
            flash('error', 'Bitte einen Namen angeben.');
        } elseif (Categories::byId($id)) {
            Categories::save($_POST, (int) $id);
            flash('success', 'Kategorie gespeichert.');
        }
        redirect('/admin/kategorien');
    });

    $r->post('/admin/kategorien/{id}/loeschen', function ($id) {
        require_admin();
        Categories::remove($id);
        flash('success', 'Kategorie gelöscht. Zugeordnete Anbieter bleiben erhalten.');
        redirect('/admin/kategorien');
    });

    /* ---------- Benutzer ---------- */
    $r->get('/admin/benutzer', function () {
        require_admin();
        admin_render('users', ['title' => 'Benutzer & Zugänge', 'users' => Users::all()]);
    });

    $r->post('/admin/benutzer', function () {
        require_admin();
        $email = mb_strtolower(str(input('email'), 150));
        $name = str(input('name'), 100);
        if (!is_email($email) || $name === '') {
            flash('error', 'Bitte Name und gültige E-Mail-Adresse angeben.');
            redirect('/admin/benutzer');
        }
        if (Users::byEmail($email)) {
            flash('error', 'Diese E-Mail-Adresse ist bereits registriert.');
            redirect('/admin/benutzer');
        }
        $user = Users::byId(Users::create($email, $name, input('role') === 'admin' ? 'admin' : 'editor'));
        if (input('send_invite') !== '') {
            invite_flash(send_invite($user), $email);
        } else {
            flash('success', 'Benutzer angelegt.');
        }
        redirect('/admin/benutzer/' . $user['id']);
    });

    $r->get('/admin/benutzer/{id}', function ($id) {
        require_admin();
        $u = Users::byId($id) ?? not_found();
        admin_render('user-edit', [
            'title' => $u['name'], 'editUser' => $u,
            'assignments' => Users::assignments((int) $u['id']),
            'allProviders' => Providers::list(['status' => null, 'sort' => 'name', 'limit' => 5000]),
        ]);
    });

    $r->post('/admin/benutzer/{id}', function ($id) {
        require_admin();
        $u = Users::byId($id) ?? not_found();
        $role = input('role') === 'admin' ? 'admin' : 'editor';
        $active = input('active') !== '';
        if ($u['role'] === 'admin' && ($role !== 'admin' || !$active) && Users::countAdmins() <= 1) {
            flash('error', 'Der letzte aktive Administrator kann nicht herabgestuft oder deaktiviert werden.');
        } else {
            Users::update((int) $u['id'], str(input('name'), 100) ?: $u['name'], $role, $active);
            flash('success', 'Benutzer gespeichert.');
        }
        redirect('/admin/benutzer/' . $u['id']);
    });

    $r->post('/admin/benutzer/{id}/einladen', function ($id) {
        require_admin();
        $u = Users::byId($id) ?? not_found();
        $first = Users::assignments((int) $u['id'])[0] ?? null;
        invite_flash(send_invite($u, $first['provider_name'] ?? null), $u['email']);
        redirect('/admin/benutzer/' . $u['id']);
    });

    $r->post('/admin/benutzer/{id}/zuweisen', function ($id) {
        require_admin();
        $u = Users::byId($id) ?? not_found();
        $p = Providers::byId(input('provider_id'));
        if (!$p) {
            flash('error', 'Bitte einen Anbieter auswählen.');
        } else {
            Users::assign((int) $u['id'], (int) $p['id'], perms_from_post());
            flash('success', "Rechte für „{$p['name']}“ gespeichert.");
        }
        redirect_back('/admin/benutzer/' . $u['id']);
    });

    $r->post('/admin/benutzer/{id}/zuweisung/{pid}/entfernen', function ($id, $pid) {
        require_admin();
        Users::unassign((int) $id, (int) $pid);
        flash('success', 'Zuweisung entfernt.');
        redirect_back("/admin/benutzer/$id");
    });

    $r->post('/admin/benutzer/{id}/loeschen', function ($id) {
        $me = require_admin();
        $u = Users::byId($id) ?? not_found();
        if ((int) $u['id'] === (int) $me['id']) {
            flash('error', 'Sie können sich nicht selbst löschen.');
        } elseif ($u['role'] === 'admin' && Users::countAdmins() <= 1) {
            flash('error', 'Der letzte Administrator kann nicht gelöscht werden.');
        } else {
            Users::remove((int) $u['id']);
            flash('success', 'Benutzer gelöscht.');
            redirect('/admin/benutzer');
        }
        redirect('/admin/benutzer/' . $u['id']);
    });
};
