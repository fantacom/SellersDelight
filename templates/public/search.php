<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="container narrow">
    <div class="page-head">
      <h1>Suche</h1>
      <form class="search-bar" action="/suche" method="get" role="search" style="box-shadow:var(--shadow);border:1px solid var(--slate-200)">
        <label for="q" class="sr-only">Suchbegriff</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Dienstleister, Leistung oder Ort …" autofocus>
        <button class="btn" type="submit">Suchen</button>
      </form>
    </div>
    <div style="margin:24px 0 40px">
      <?php if ($q !== '' && $providers): ?>
        <p class="muted small"><?= count($providers) ?> Treffer für „<?= e($q) ?>“</p>
        <div class="provider-list"><?php foreach ($providers as $i => $p): ?><?= partial('provider-row', ['p' => $p, 'rank' => $i + 1, 'showCats' => true]) ?><?php endforeach ?></div>
      <?php elseif ($q !== ''): ?>
        <div class="card empty"><p>Keine Treffer für „<?= e($q) ?>“.</p><a class="btn btn-secondary" href="/anbieter-eintragen">Anbieter eintragen</a></div>
      <?php endif ?>
    </div>
  </div>
</main>
<?= partial('foot') ?>
