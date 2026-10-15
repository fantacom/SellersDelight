<?php
declare(strict_types=1);

const SORT_LABELS = ['rating' => 'Beste Bewertung', 'reviews' => 'Meiste Bewertungen', 'name' => 'Name (A–Z)', 'newest' => 'Neueste'];

/** Baut aus abgeschickten Formulardaten ein Objekt zum erneuten Befüllen des Formulars. */
function form_to_provider(array $body): array
{
    $names = to_array($body['product_name'] ?? null);
    $products = [];
    foreach ($names as $i => $name) {
        $products[] = [
            'name' => $name,
            'description' => to_array($body['product_description'] ?? null)[$i] ?? '',
            'url' => to_array($body['product_url'] ?? null)[$i] ?? '',
            'price_info' => to_array($body['product_price'] ?? null)[$i] ?? '',
        ];
    }
    return array_merge($body, [
        'category_ids' => array_map('intval', to_array($body['category_ids'] ?? null)),
        'products' => $products,
    ]);
}

function render_provider(array $provider, array $form = [], array $errors = []): void
{
    render('public/provider', [
        'title' => $provider['name'],
        'provider' => $provider,
        'reviews' => Reviews::forProvider((int) $provider['id']),
        'distribution' => Providers::ratingDistribution((int) $provider['id']),
        'form' => $form,
        'errors' => $errors,
    ], $errors ? 422 : 200);
}

return function (Router $r) {
    $r->get('/', function () {
        render('public/home', [
            'categories' => nav_categories(),
            'topRated' => Providers::topRated(6),
            'recentReviews' => Reviews::recentApproved(3),
        ]);
    });

    $r->get('/kategorie/{slug}', function ($slug) {
        $category = Categories::bySlug($slug) ?? not_found();
        $sort = isset(Providers::SORTS[query('sort')]) ? query('sort') : 'rating';
        render('public/category', [
            'title' => $category['name'],
            'category' => $category,
            'sort' => $sort,
            'providers' => Providers::list(['categoryId' => $category['id'], 'sort' => $sort]),
        ]);
    });

    $r->get('/suche', function () {
        $q = str(query('q'), 100);
        render('public/search', [
            'title' => $q !== '' ? "Suche: $q" : 'Suche',
            'q' => $q,
            'providers' => $q !== '' ? Providers::list(['search' => $q, 'limit' => 100]) : [],
        ]);
    });

    $r->get('/anbieter/{slug}', function ($slug) {
        $p = Providers::bySlug($slug);
        if (!$p || ($p['status'] !== 'published' && !is_admin())) {
            not_found();
        }
        render_provider($p);
    });

    $r->post('/anbieter/{slug}/bewerten', function ($slug) {
        $p = Providers::bySlug($slug);
        if (!$p || $p['status'] !== 'published') {
            not_found();
        }
        $thanks = 'Vielen Dank! Ihre Bewertung wird geprüft und anschließend veröffentlicht.';
        // Honeypot: für Menschen unsichtbar, von Bots oft ausgefüllt.
        if (input('homepage') !== '') {
            flash('success', $thanks);
            redirect('/anbieter/' . $p['slug']);
        }
        $form = [
            'rating' => (int) input('rating', 0),
            'title' => str(input('title'), 120),
            'comment' => str(input('comment'), 4000),
            'author_name' => str(input('author_name'), 80),
            'author_email' => mb_strtolower(str(input('author_email'), 150)),
            'consent' => input('consent') !== '',
        ];
        $errors = [];
        if ($form['rating'] < 1 || $form['rating'] > 5) $errors[] = 'Bitte vergeben Sie 1 bis 5 Sterne.';
        if (mb_strlen($form['comment']) < 20) $errors[] = 'Bitte beschreiben Sie Ihre Erfahrung in mindestens 20 Zeichen.';
        if ($form['author_name'] === '') $errors[] = 'Bitte geben Sie einen Namen an (wird öffentlich angezeigt).';
        if (!is_email($form['author_email'])) $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an (wird nicht veröffentlicht).';
        if (!$form['consent']) $errors[] = 'Bitte bestätigen Sie die Datenschutzhinweise.';
        if (!$errors && Reviews::existsFor((int) $p['id'], $form['author_email'])) {
            $errors[] = 'Mit dieser E-Mail-Adresse wurde dieser Anbieter bereits bewertet.';
        }
        if (!$errors && !rate_limit('review:' . client_ip(), 3600, 10)) {
            $errors[] = 'Zu viele Bewertungen in kurzer Zeit. Bitte später erneut versuchen.';
        }
        if ($errors) {
            render_provider($p, $form, $errors);
        }
        Reviews::create((int) $p['id'], $form['rating'], $form['title'], $form['comment'], $form['author_name'], $form['author_email']);
        notify_admin("Neue Bewertung für {$p['name']}", "{$form['rating']}/5 Sterne von {$form['author_name']}\n\n{$form['comment']}\n\nFreigabe: " . base_url() . '/admin/bewertungen');
        flash('success', $thanks);
        redirect('/anbieter/' . $p['slug'] . '#bewertungen');
    });

    $r->get('/anbieter-eintragen', function () {
        $preset = Categories::bySlug((string) query('kategorie'));
        render('public/submit', [
            'title' => 'Anbieter eintragen',
            'provider' => ['category_ids' => $preset ? [(int) $preset['id']] : [], 'products' => []],
            'errors' => [],
        ]);
    });

    $r->post('/anbieter-eintragen', function () {
        $thanks = 'Vielen Dank! Wir prüfen den Eintrag und schalten ihn anschließend frei.';
        if (input('homepage') !== '') {
            flash('success', $thanks);
            redirect('/');
        }
        $errors = [];
        if (str(input('name')) === '') $errors[] = 'Bitte geben Sie den Firmennamen an.';
        if (!to_array($_POST['category_ids'] ?? null)) $errors[] = 'Bitte wählen Sie mindestens eine Kategorie.';
        if (str(input('short_description')) === '') $errors[] = 'Bitte geben Sie eine Kurzbeschreibung an.';
        if (str(input('submitted_by_name')) === '') $errors[] = 'Bitte geben Sie Ihren Namen an.';
        if (!is_email(input('submitted_by_email'))) $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
        if (input('consent') === '') $errors[] = 'Bitte bestätigen Sie die Datenschutzhinweise.';
        if (!$errors && !rate_limit('submit:' . client_ip(), 3600, 5)) {
            $errors[] = 'Zu viele Einreichungen in kurzer Zeit. Bitte später erneut versuchen.';
        }
        if ($errors) {
            render('public/submit', ['title' => 'Anbieter eintragen', 'provider' => form_to_provider($_POST), 'errors' => $errors], 422);
        }
        Providers::save($_POST, null, ['master' => true, 'description' => true, 'categories' => true, 'products' => true], 'pending');
        notify_admin('Neuer Anbieter eingereicht: ' . str(input('name')),
            'Eingereicht von ' . str(input('submitted_by_name')) . ' <' . str(input('submitted_by_email')) . ">\n\nPrüfen: " . base_url() . '/admin/anbieter?status=pending');
        flash('success', $thanks);
        redirect('/');
    });

    $r->get('/impressum', fn() => render('public/legal', ['title' => 'Impressum', 'page' => 'impressum']));
    $r->get('/datenschutz', fn() => render('public/legal', ['title' => 'Datenschutz', 'page' => 'datenschutz']));
};
