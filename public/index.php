<?php
declare(strict_types=1);

// Lokaler Testserver (php -S): vorhandene Dateien direkt ausliefern.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; form-action 'self'");

session_name('sd_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$GLOBALS['FLASH'] = take_flash();

try {
    $router = new Router();
    foreach (['setup', 'public', 'auth', 'account', 'admin'] as $file) {
        (require APP_ROOT . "/app/routes/$file.php")($router);
    }

    $path = current_path();
    // Ohne Benutzer gibt es nur die Ersteinrichtung.
    if ($path !== '/einrichtung' && Users::count() === 0) {
        redirect('/einrichtung');
    }
    $router->dispatch($_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET', $path);
} catch (Throwable $e) {
    error_log((string) $e);
    if (config('debug')) {
        throw $e;
    }
    render_error(500, 'Fehler', 'Es ist ein unerwarteter Fehler aufgetreten.');
}
