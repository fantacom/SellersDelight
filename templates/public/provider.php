<?= partial('head', get_defined_vars()) ?>
<?php
$addr = array_values(array_filter([$provider['street'], trim($provider['zip'] . ' ' . $provider['city']), $provider['country']]));
$total = $provider['review_count'];
$legal = $provider['legal_form'] && !str_contains($provider['name'], $provider['legal_form']) ? $provider['legal_form'] : '';
$contact = config('contact_email');
?>
<main>
  <?php if ($provider['status'] !== 'published'): ?>
    <div class="container"><div class="alert alert-info" style="margin-top:16px">Vorschau: Dieser Anbieter ist nicht öffentlich. <a href="/admin/anbieter/<?= (int) $provider['id'] ?>">Im Backend bearbeiten</a></div></div>
  <?php endif ?>
  <section class="profile-head">
    <div class="container">
      <div class="avatar lg"><?= e(initials($provider['name'])) ?></div>
      <div class="summary">
        <div class="breadcrumb"><a href="/">Start</a><?php if ($provider['categories']): $c0 = $provider['categories'][0]; ?> / <a href="/kategorie/<?= e($c0['slug']) ?>"><?= e($c0['name']) ?></a><?php endif ?></div>
        <h1><?= e($provider['name']) ?><?php if ($legal): ?> <span class="muted" style="font-weight:500;font-size:.6em"><?= e($legal) ?></span><?php endif ?></h1>
        <p class="muted" style="margin:0 0 10px"><?= e($provider['short_description']) ?></p>
        <div class="tags"><?php foreach ($provider['categories'] as $c): ?><a class="tag" href="/kategorie/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach ?></div>
      </div>
      <div class="head-rating">
        <div class="score"><?= format_rating($provider['avg_rating']) ?></div>
        <?= partial('stars', ['value' => $provider['avg_rating'], 'size' => 'lg']) ?>
        <div class="small muted"><?= $total ?> <?= $total === 1 ? 'Bewertung' : 'Bewertungen' ?></div>
        <a class="btn btn-sm" href="#bewerten" style="margin-top:10px">Jetzt bewerten</a>
      </div>
    </div>
  </section>

  <div class="container">
    <?= partial('flash', ['flash' => $flash]) ?>
    <div class="profile-layout">
      <div>
        <?php if ($provider['description']): ?>
        <div class="card"><h2>Über <?= e($provider['name']) ?></h2><div class="prose"><?= paragraphs($provider['description']) ?></div></div>
        <?php endif ?>

        <?php if ($provider['products']): ?>
        <div class="card">
          <h2>Produkte &amp; Leistungen</h2>
          <div class="products">
            <?php foreach ($provider['products'] as $pr): ?>
            <div class="product">
              <div>
                <h4><?= e($pr['name']) ?></h4>
                <?php if ($pr['description']): ?><p><?= e($pr['description']) ?></p><?php endif ?>
                <?php if ($pr['price_info']): ?><div class="price"><?= e($pr['price_info']) ?></div><?php endif ?>
              </div>
              <?php if ($pr['url']): ?><a class="btn btn-secondary btn-sm nowrap" href="<?= e($pr['url']) ?>" target="_blank" rel="noopener nofollow">Zum Angebot ↗</a><?php endif ?>
            </div>
            <?php endforeach ?>
          </div>
        </div>
        <?php endif ?>

        <div class="card" id="bewertungen">
          <h2>Bewertungen</h2>
          <?php if ($reviews): foreach ($reviews as $rv): ?>
            <article class="review">
              <div class="review-head">
                <?= partial('stars', ['value' => $rv['rating']]) ?>
                <span class="who"><?= format_date($rv['created_at']) ?></span>
              </div>
              <?php if ($rv['title']): ?><h4><?= e($rv['title']) ?></h4><?php endif ?>
              <div class="prose"><?= paragraphs($rv['comment']) ?></div>
              <div class="who">— <?= e($rv['author_name']) ?></div>
            </article>
          <?php endforeach; else: ?>
            <p class="muted">Noch keine Bewertungen. Seien Sie der Erste!</p>
          <?php endif ?>
        </div>

        <div class="card" id="bewerten">
          <h2>Erfahrung teilen</h2>
          <p class="muted small">Ihre Bewertung wird vor der Veröffentlichung geprüft. Ihre E-Mail-Adresse wird nicht angezeigt.</p>
          <?= partial('errors', ['errors' => $errors]) ?>
          <form method="post" action="/anbieter/<?= e($provider['slug']) ?>/bewerten#bewerten" novalidate>
            <?= csrf_field() ?>
            <div class="hp" aria-hidden="true"><label>Homepage <input type="text" name="homepage" tabindex="-1" autocomplete="off"></label></div>
            <div class="field">
              <label id="rating-label">Ihre Bewertung *</label>
              <div class="star-input" role="radiogroup" aria-labelledby="rating-label">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                  <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>" <?= (int) ($form['rating'] ?? 0) === $s ? 'checked' : '' ?>>
                  <label for="star<?= $s ?>" title="<?= $s ?> Sterne"><span class="sr-only"><?= $s ?> Sterne</span><?= partial('icon', ['name' => 'star', 'filled' => true]) ?></label>
                <?php endfor ?>
              </div>
            </div>
            <div class="field"><label for="r-title">Überschrift <span class="hint">optional</span></label><input id="r-title" type="text" name="title" value="<?= e($form['title'] ?? '') ?>" maxlength="120"></div>
            <div class="field"><label for="r-comment">Ihre Erfahrung *</label><textarea id="r-comment" name="comment" maxlength="4000" placeholder="Was lief gut, was weniger? Wie war Kommunikation, Qualität, Preis-Leistung?"><?= e($form['comment'] ?? '') ?></textarea></div>
            <div class="grid-2">
              <div class="field"><label for="r-name">Name * <span class="hint">wird angezeigt, z. B. „Max M.“ oder Firmenname</span></label><input id="r-name" type="text" name="author_name" value="<?= e($form['author_name'] ?? '') ?>" maxlength="80"></div>
              <div class="field"><label for="r-email">E-Mail * <span class="hint">wird nicht veröffentlicht</span></label><input id="r-email" type="email" name="author_email" value="<?= e($form['author_email'] ?? '') ?>" maxlength="150"></div>
            </div>
            <div class="field"><label class="check"><input type="checkbox" name="consent" value="1" <?= !empty($form['consent']) ? 'checked' : '' ?>> <span>Ich habe die <a href="/datenschutz" target="_blank">Datenschutzhinweise</a> gelesen und bestätige, dass meine Bewertung auf eigener Erfahrung beruht.</span></label></div>
            <button class="btn" type="submit">Bewertung absenden</button>
          </form>
        </div>
      </div>

      <aside>
        <div class="card">
          <h3>Unternehmensdaten</h3>
          <ul class="facts">
            <li><span class="k">Firma</span><span class="v"><?= e(trim($provider['name'] . ' ' . $legal)) ?></span></li>
            <?php if ($addr): ?><li><span class="k">Adresse</span><span class="v"><?= implode('<br>', array_map('e', $addr)) ?></span></li><?php endif ?>
            <?php if ($provider['founded_year']): ?><li><span class="k">Gegründet</span><span class="v"><?= (int) $provider['founded_year'] ?> (seit <?= (int) date('Y') - (int) $provider['founded_year'] ?> Jahren)</span></li><?php endif ?>
            <?php if ($provider['employees']): ?><li><span class="k">Mitarbeiter</span><span class="v"><?= e($provider['employees']) ?></span></li><?php endif ?>
            <?php if ($provider['website']): ?><li><span class="k">Website</span><span class="v"><a href="<?= e($provider['website']) ?>" target="_blank" rel="noopener nofollow"><?= e(host_of($provider['website'])) ?></a></span></li><?php endif ?>
            <?php if ($provider['email']): ?><li><span class="k">E-Mail</span><span class="v"><a href="mailto:<?= e($provider['email']) ?>"><?= e($provider['email']) ?></a></span></li><?php endif ?>
            <?php if ($provider['phone']): ?><li><span class="k">Telefon</span><span class="v"><a href="tel:<?= e(preg_replace('/[^+\d]/', '', $provider['phone'])) ?>"><?= e($provider['phone']) ?></a></span></li><?php endif ?>
          </ul>
        </div>
        <?php if ($total): ?>
        <div class="card">
          <h3>Bewertungsverteilung</h3>
          <div class="dist">
            <?php for ($s = 5; $s >= 1; $s--): $n = $distribution[$s]; ?>
              <span><?= $s ?> ★</span><span class="bar"><span style="width:<?= round($n / $total * 100) ?>%"></span></span><span><?= $n ?></span>
            <?php endfor ?>
          </div>
        </div>
        <?php endif ?>
        <div class="card">
          <h3>Sind Sie <?= e($provider['name']) ?>?</h3>
          <p class="small muted" style="margin:0">Pflegen Sie Ihr Profil selbst:
            <?php if ($contact): ?><a href="mailto:<?= e($contact) ?>?subject=<?= rawurlencode('Zugang für ' . $provider['name']) ?>">Schreiben Sie uns</a><?php else: ?>Schreiben Sie uns<?php endif ?>, wir richten Ihnen einen Zugang ein.</p>
        </div>
      </aside>
    </div>
  </div>
</main>
<?= partial('foot') ?>
