<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="container">
    <div class="page-head">
      <div class="breadcrumb"><a href="/">Start</a> / <a href="/#kategorien">Kategorien</a> / <?= e($category['name']) ?></div>
      <div style="display:flex;gap:16px;align-items:center">
        <span class="icon-tile lg"><?= partial('icon', ['name' => $category['icon']]) ?></span>
        <div><h1 style="margin:0"><?= e($category['name']) ?></h1><p class="muted" style="margin:0"><?= e($category['description']) ?></p></div>
      </div>
      <?= partial('flash', ['flash' => $flash]) ?>
    </div>

    <div class="toolbar" style="margin-top:24px;justify-content:space-between">
      <span class="muted small"><?= count($providers) ?> Anbieter</span>
      <div class="tabs" style="margin:0;border:0">
        <?php foreach (SORT_LABELS as $k => $label): ?><a href="?sort=<?= $k ?>" class="<?= $sort === $k ? 'active' : '' ?>"><?= $label ?></a><?php endforeach ?>
      </div>
    </div>

    <?php if ($providers): ?>
      <div class="provider-list">
        <?php foreach ($providers as $i => $p): ?><?= partial('provider-row', ['p' => $p, 'rank' => $i + 1]) ?><?php endforeach ?>
      </div>
      <p class="small muted" style="margin-top:14px">Die Reihenfolge nach „Beste Bewertung“ berücksichtigt Durchschnitt und Anzahl der Bewertungen, damit einzelne Bewertungen nicht überproportional zählen.</p>
    <?php else: ?>
      <div class="card empty">
        <span class="icon-tile lg"><?= partial('icon', ['name' => $category['icon']]) ?></span>
        <p>In dieser Kategorie gibt es noch keine Einträge.</p>
      </div>
    <?php endif ?>

    <div class="cta-band" style="margin:28px 0 20px">
      <div><h3>Anbieter fehlt?</h3><p>Tragen Sie einen Anbieter aus dem Bereich „<?= e($category['name']) ?>“ ein, den Sie empfehlen können.</p></div>
      <a class="btn" href="/anbieter-eintragen?kategorie=<?= e($category['slug']) ?>">Anbieter eintragen</a>
    </div>
  </div>
</main>
<?= partial('foot') ?>
