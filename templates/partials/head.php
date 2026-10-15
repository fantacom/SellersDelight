<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ? e($title) . " · Seller's Delight" : "Seller's Delight – Die besten Dienstleister für E-Commerce-Seller" ?></title>
  <meta name="description" content="Bewertete Dienstleister für Amazon- und E-Commerce-Seller: Fotostudios, Agenturen, Steuerberater, Fulfillment und mehr.">
  <link rel="icon" href="/img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/css/style.css?v=2">
  <script src="/js/app.js?v=2" defer></script>
</head>
<body>
<header class="site-header">
  <div class="container">
    <a class="brand" href="/"><?= partial('logo-mark') ?><span>Seller's <span class="accent">Delight</span></span></a>
    <button class="nav-toggle" type="button" aria-label="Menü" aria-expanded="false"><span style="display:block;width:24px;height:24px"><?= partial('icon', ['name' => 'menu']) ?></span></button>
    <nav class="main-nav" aria-label="Hauptnavigation">
      <a href="/#kategorien">Kategorien</a>
      <a href="/suche" class="<?= $path === '/suche' ? 'active' : '' ?>">Suche</a>
      <?php if ($user): ?>
        <a href="<?= home_for($user) ?>"><?= $user['role'] === 'admin' ? 'Backend' : 'Mein Konto' ?></a>
        <form method="post" action="/logout"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Abmelden</button></form>
      <?php else: ?>
        <a href="/login">Anmelden</a>
      <?php endif ?>
      <a class="btn btn-sm" href="/anbieter-eintragen">Anbieter eintragen</a>
    </nav>
  </div>
</header>
