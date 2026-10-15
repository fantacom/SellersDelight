<?php
declare(strict_types=1);

// Installationsassistent: läuft nur, solange config.php fehlt. Prüft die Datenbankverbindung
// und schreibt anschließend config.php. Danach ist dieser Assistent nicht mehr erreichbar.

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$f = [
    'host' => '', 'name' => '', 'user' => '', 'pass' => '',
    'base_url' => ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'mail_from' => 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'sellersdelight.de'),
    'contact_email' => '', 'admin_notify_email' => '',
];
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($f as $k => $_) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $f['base_url'] = rtrim($f['base_url'], '/');
    try {
        new PDO("mysql:host={$f['host']};port=3306;dbname={$f['name']};charset=utf8mb4", $f['user'], $f['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    } catch (PDOException $e) {
        $error = 'Verbindung zur Datenbank fehlgeschlagen. Bitte Hostname, Datenbankname, Benutzer und Passwort prüfen. (' . $e->getMessage() . ')';
    }
    if (!$error) {
        $config = [
            'db' => ['host' => $f['host'], 'port' => 3306, 'name' => $f['name'], 'user' => $f['user'], 'pass' => $f['pass']],
            'base_url' => $f['base_url'],
            'mail_enabled' => true,
            'mail_from' => $f['mail_from'],
            'mail_from_name' => "Seller's Delight",
            'contact_email' => $f['contact_email'],
            'admin_notify_email' => $f['admin_notify_email'],
            'debug' => false,
        ];
        $php = "<?php\n// Erzeugt vom Installationsassistenten am " . date('d.m.Y H:i') . "\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents(APP_ROOT . '/config.php', $php, LOCK_EX) === false) {
            $error = 'config.php konnte nicht geschrieben werden. Bitte Schreibrechte für das Hauptverzeichnis prüfen oder config.sample.php manuell als config.php anlegen.';
        } else {
            @chmod(APP_ROOT . '/config.php', 0640);
            header('Location: /einrichtung', true, 303);
            exit;
        }
    }
}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation · Seller's Delight</title><link rel="icon" href="/img/favicon.svg"><link rel="stylesheet" href="/css/style.css?v=2"></head>
<body><main><div class="auth-wrap" style="max-width:620px">
  <div class="logo"><img src="/img/logo.svg" alt="Seller's Delight"></div>
  <div class="card">
    <h1 style="font-size:1.4rem">Installation</h1>
    <p class="muted small">Tragen Sie die Zugangsdaten Ihrer MySQL-Datenbank ein. Bei IONOS finden Sie diese unter <strong>Hosting → Datenbanken</strong>. Dieser Schritt ist nur einmal nötig.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= $h($error) ?></div><?php endif ?>
    <form method="post">
      <h3>Datenbank</h3>
      <div class="field"><label for="host">Hostname <span class="hint">z. B. db5012345678.hosting-data.io</span></label><input id="host" type="text" name="host" value="<?= $h($f['host']) ?>" required></div>
      <div class="grid-2">
        <div class="field"><label for="name">Datenbankname <span class="hint">z. B. dbs12345678</span></label><input id="name" type="text" name="name" value="<?= $h($f['name']) ?>" required></div>
        <div class="field"><label for="user">Benutzername <span class="hint">z. B. dbu12345678</span></label><input id="user" type="text" name="user" value="<?= $h($f['user']) ?>" required></div>
      </div>
      <div class="field"><label for="pass">Passwort</label><input id="pass" type="password" name="pass" value="<?= $h($f['pass']) ?>" required></div>
      <h3>Website &amp; E-Mail</h3>
      <div class="field"><label for="base_url">Adresse der Website</label><input id="base_url" type="text" name="base_url" value="<?= $h($f['base_url']) ?>" required></div>
      <div class="grid-2">
        <div class="field"><label for="mail_from">Absender für E-Mails <span class="hint">Adresse Ihrer Domain</span></label><input id="mail_from" type="email" name="mail_from" value="<?= $h($f['mail_from']) ?>" required></div>
        <div class="field"><label for="contact_email">Kontaktadresse <span class="hint">optional</span></label><input id="contact_email" type="email" name="contact_email" value="<?= $h($f['contact_email']) ?>"></div>
      </div>
      <div class="field"><label for="admin_notify_email">Benachrichtigungen an <span class="hint">optional: E-Mail bei neuen Bewertungen/Einträgen</span></label><input id="admin_notify_email" type="email" name="admin_notify_email" value="<?= $h($f['admin_notify_email']) ?>"></div>
      <button class="btn" type="submit" style="width:100%">Verbindung prüfen &amp; installieren</button>
    </form>
  </div>
</div></main></body></html>
