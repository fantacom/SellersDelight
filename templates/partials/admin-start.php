<?= partial('head', get_defined_vars()) ?>
<?php
$nav = [
    ['/admin', 'home', 'Übersicht', null],
    ['/admin/bewertungen', 'message', 'Bewertungen', $pending['reviews']],
    ['/admin/anbieter', 'list', 'Anbieter', $pending['providers']],
    ['/admin/aenderungen', 'pen', 'Profil-Änderungen', $pending['changes']],
    ['/admin/kategorien', 'tag', 'Kategorien', null],
    ['/admin/benutzer', 'users', 'Benutzer & Zugänge', null],
];
$isActive = fn($href) => $href === '/admin' ? $path === '/admin' : ($path === $href || str_starts_with($path, $href . '/'));
?>
<div class="admin-shell">
  <aside class="admin-side">
    <nav aria-label="Backend">
      <?php foreach ($nav as [$href, $icon, $label, $count]): ?>
        <a href="<?= $href ?>" class="<?= $isActive($href) ? 'active' : '' ?>"><?= partial('icon', ['name' => $icon]) ?><?= e($label) ?><?php if ($count): ?><span class="count"><?= (int) $count ?></span><?php endif ?></a>
      <?php endforeach ?>
      <div class="sep"></div>
      <a href="/konto/passwort"><?= partial('icon', ['name' => 'key']) ?>Passwort ändern</a>
      <a href="/" target="_blank"><?= partial('icon', ['name' => 'external']) ?>Website ansehen</a>
    </nav>
  </aside>
  <div class="admin-main">
    <?= partial('flash', ['flash' => $flash]) ?>
