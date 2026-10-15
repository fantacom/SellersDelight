<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        exit("config.php fehlt. Bitte zuerst die Installation im Browser durchführen.\n");
    }
    require APP_ROOT . '/app/install.php';
    exit;
}
$GLOBALS['CONFIG'] = require $configFile;

require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/db.php';
require APP_ROOT . '/app/models.php';
require APP_ROOT . '/app/auth.php';
require APP_ROOT . '/app/mail.php';
require APP_ROOT . '/app/router.php';

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');

