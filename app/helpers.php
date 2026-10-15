<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    return $GLOBALS['CONFIG'][$key] ?? $default;
}

/** HTML-Escaping für Ausgaben in Templates. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function str($value, int $max = 500): string
{
    if (is_array($value)) {
        return '';
    }
    return mb_substr(trim((string) ($value ?? '')), 0, $max);
}

function to_array($value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    return is_array($value) ? array_values($value) : [$value];
}

function is_email($value): bool
{
    return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function slugify(string $input): string
{
    $s = mb_strtolower($input);
    $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    if (function_exists('transliterator_transliterate')) {
        $s = transliterator_transliterate('Any-Latin; Latin-ASCII', $s) ?: $s;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = substr(trim($s, '-'), 0, 80);
    return $s !== '' ? $s : 'eintrag';
}

/** Liefert einen in `$table` noch freien Slug (optional unter Ausschluss der eigenen ID). */
function unique_slug(string $table, string $base, ?int $excludeId = null): string
{
    $root = slugify($base);
    for ($i = 1; ; $i++) {
        $candidate = $i === 1 ? $root : "$root-$i";
        $id = q_val("SELECT id FROM $table WHERE slug = ?", [$candidate]);
        if ($id === null || (int) $id === $excludeId) {
            return $candidate;
        }
    }
}

/** Ergänzt fehlendes Protokoll und lässt nur http(s) zu. */
function normalize_url($value): string
{
    $v = str($value, 300);
    if ($v === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $v)) {
        $v = 'https://' . $v;
    }
    $parts = parse_url($v);
    if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
        return '';
    }
    return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
}

function format_date(?string $value): string
{
    return $value ? date('d.m.Y', strtotime($value)) : '';
}

function format_rating($value): string
{
    return $value ? number_format((float) $value, 1, ',', '.') : '–';
}

/** Escaped Text mit Absätzen/Zeilenumbrüchen als HTML. */
function paragraphs(?string $text): string
{
    $out = [];
    foreach (preg_split('/\n\s*\n/', str_replace("\r", '', (string) $text)) as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[] = '<p>' . nl2br(e($p), false) . '</p>';
        }
    }
    return implode("\n", $out);
}

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N} ]/u', '', $name)));
    $s = '';
    foreach (array_slice(array_filter($words), 0, 2) as $w) {
        $s .= mb_substr($w, 0, 1);
    }
    return mb_strtoupper($s) ?: '?';
}

function host_of(string $url): string
{
    $h = parse_url($url, PHP_URL_HOST) ?: $url;
    return preg_replace('/^www\./', '', $h);
}

/* ---------- Request / Response ---------- */

function base_url(): string
{
    $configured = rtrim((string) config('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function input(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function query(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_array($v) ? $default : $v;
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** Leitet auf den Referer zurück, sofern er von dieser Seite stammt. */
function redirect_back(string $fallback): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === parse_url(base_url(), PHP_URL_HOST)) {
        redirect($ref);
    }
    redirect($fallback);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = token(24);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        render_error(403, 'Sitzung abgelaufen', 'Das Formular ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
    }
}

/** Einfacher Ratelimiter über die Datenbank (geteiltes Hosting hat keinen dauerhaften Prozess-Speicher). */
function rate_limit(string $key, int $windowSeconds, int $max): bool
{
    $since = time() - $windowSeconds;
    q('DELETE FROM rate_limits WHERE ts < ?', [time() - 86400]);
    $count = (int) q_val('SELECT COUNT(*) FROM rate_limits WHERE k = ? AND ts >= ?', [$key, $since]);
    q('INSERT INTO rate_limits (k, ts) VALUES (?, ?)', [$key, time()]);
    return $count < $max;
}

/* ---------- Templates ---------- */

/** Rendert ein Template mit Layout-Daten und beendet die Anfrage. */
function render(string $template, array $vars = [], int $status = 200): void
{
    http_response_code($status);
    echo view($template, $vars);
    exit;
}

function view(string $template, array $vars = []): string
{
    $vars += [
        'title' => null,
        'user' => current_user(),
        'path' => current_path(),
        'flash' => $GLOBALS['FLASH'] ?? [],
    ];
    extract($vars, EXTR_SKIP);
    ob_start();
    include APP_ROOT . '/templates/' . $template . '.php';
    return ob_get_clean();
}

/** Bindet ein Teil-Template ein; übernimmt die übergebenen Variablen. */
function partial(string $name, array $vars = []): string
{
    return view('partials/' . $name, $vars);
}

function render_error(int $status, string $title, string $message): void
{
    render('public/error', ['title' => $title, 'message' => $message], $status);
}

function current_path(): string
{
    return rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
}

function nav_categories(): array
{
    static $cats = null;
    return $cats ??= Categories::all();
}
