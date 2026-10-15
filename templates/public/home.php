<?= partial('head', get_defined_vars()) ?>
<main>
  <section class="hero">
    <div class="container">
      <?= partial('flash', ['flash' => $flash]) ?>
      <h1>Die besten Dienstleister für <span class="accent nowrap">E‑Commerce‑Seller</span>.</h1>
      <p class="lead">Fotostudios, Agenturen, Steuerberater und Fulfillment-Partner – bewertet von Sellern für Seller. Jede Bewertung wird vor der Veröffentlichung geprüft.</p>
      <form class="search-bar" action="/suche" method="get" role="search">
        <label for="q" class="sr-only">Dienstleister suchen</label>
        <input id="q" type="search" name="q" placeholder="Dienstleister, Leistung oder Ort suchen …">
        <button class="btn" type="submit">Suchen</button>
      </form>
      <div class="hero-stats">
        <div><strong><?= count($categories) ?></strong>Kategorien</div>
        <div><strong><?= (int) q_val("SELECT COUNT(*) FROM providers WHERE status = 'published'") ?></strong>Anbieter</div>
        <div><strong>100 %</strong>geprüfte Bewertungen</div>
      </div>
    </div>
  </section>

  <section class="section" id="kategorien">
    <div class="container">
      <div class="section-head">
        <div><h2>Kategorien</h2><p class="muted" style="margin:0">Wählen Sie einen Bereich und sehen Sie die bestbewerteten Anbieter.</p></div>
      </div>
      <div class="cat-grid">
        <?php foreach ($categories as $c): ?>
        <a class="cat-card" href="/kategorie/<?= e($c['slug']) ?>">
          <span class="icon-tile"><?= partial('icon', ['name' => $c['icon']]) ?></span>
          <span>
            <h3><?= e($c['name']) ?></h3>
            <p><?= e($c['description']) ?></p>
            <span class="count"><?= (int) $c['provider_count'] ?> Anbieter →</span>
          </span>
        </a>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <?php if ($topRated): ?>
  <section class="section" style="padding-top:0">
    <div class="container">
      <div class="section-head"><h2>Bestbewertet</h2></div>
      <div class="provider-grid">
        <?php foreach ($topRated as $p): ?>
        <a class="provider-card" href="/anbieter/<?= e($p['slug']) ?>">
          <div class="top">
            <div class="avatar"><?= e(initials($p['name'])) ?></div>
            <div><h3><?= e($p['name']) ?></h3><?= partial('stars', ['value' => $p['avg_rating'], 'size' => 'sm']) ?> <span class="small muted"><?= format_rating($p['avg_rating']) ?> · <?= $p['review_count'] ?></span></div>
          </div>
          <p><?= e($p['short_description']) ?></p>
          <div class="tags"><?php foreach ($p['categories'] as $c): ?><span class="tag"><?= e($c['name']) ?></span><?php endforeach ?></div>
        </a>
        <?php endforeach ?>
      </div>
    </div>
  </section>
  <?php endif ?>

  <?php if ($recentReviews): ?>
  <section class="section" style="padding-top:0">
    <div class="container">
      <div class="section-head"><h2>Neueste Erfahrungsberichte</h2></div>
      <div class="provider-grid">
        <?php foreach ($recentReviews as $rv): ?>
        <a class="provider-card" href="/anbieter/<?= e($rv['provider_slug']) ?>#bewertungen">
          <div><?= partial('stars', ['value' => $rv['rating'], 'size' => 'sm']) ?></div>
          <h3><?= e($rv['title'] ?: $rv['provider_name']) ?></h3>
          <p><?= e(mb_strlen($rv['comment']) > 180 ? mb_substr($rv['comment'], 0, 180) . ' …' : $rv['comment']) ?></p>
          <span class="small muted"><?= e($rv['author_name']) ?> über <strong><?= e($rv['provider_name']) ?></strong></span>
        </a>
        <?php endforeach ?>
      </div>
    </div>
  </section>
  <?php endif ?>

  <section class="section" style="padding-top:0">
    <div class="container">
      <div class="cta-band">
        <div><h3>Sie kennen einen guten Dienstleister?</h3><p>Tragen Sie ihn ein – nach kurzer Prüfung ist er für alle Seller sichtbar.</p></div>
        <a class="btn btn-lg" href="/anbieter-eintragen">Anbieter eintragen</a>
      </div>
    </div>
  </section>
</main>
<?= partial('foot') ?>
