<?php
$v = fn($k) => e($provider[$k] ?? '');
$can = fn($s) => !empty($sections[$s]);
$selected = array_map('intval', $provider['category_ids'] ?? []);
$productList = !empty($provider['products']) ? $provider['products'] : ($can('products') ? [[]] : []);
$locked = '<p class="muted small">Für diesen Bereich haben Sie keine Bearbeitungsrechte.</p>';
?>
<?= csrf_field() ?>

<div class="card">
  <h2>Firmendaten</h2>
  <fieldset <?= $can('master') ? '' : 'disabled' ?>>
    <?= $can('master') ? '' : $locked ?>
    <div class="grid-2">
      <div class="field"><label for="f-name">Firmenname *</label><input id="f-name" type="text" name="name" value="<?= $v('name') ?>" required maxlength="150"></div>
      <div class="field"><label for="f-legal">Rechtsform <span class="hint">z. B. GmbH, UG, Einzelunternehmen</span></label><input id="f-legal" type="text" name="legal_form" value="<?= $v('legal_form') ?>" maxlength="40"></div>
    </div>
    <div class="field"><label for="f-street">Straße und Hausnummer</label><input id="f-street" type="text" name="street" value="<?= $v('street') ?>" maxlength="150"></div>
    <div class="grid-zip">
      <div class="field"><label for="f-zip">PLZ</label><input id="f-zip" type="text" name="zip" value="<?= $v('zip') ?>" maxlength="15"></div>
      <div class="field"><label for="f-city">Ort</label><input id="f-city" type="text" name="city" value="<?= $v('city') ?>" maxlength="100"></div>
    </div>
    <div class="grid-3">
      <div class="field"><label for="f-country">Land</label><input id="f-country" type="text" name="country" value="<?= ($provider['country'] ?? '') !== '' ? $v('country') : 'Deutschland' ?>" maxlength="60"></div>
      <div class="field"><label for="f-founded">Gegründet (Jahr)</label><input id="f-founded" type="number" name="founded_year" value="<?= $v('founded_year') ?>" min="1800" max="2100"></div>
      <div class="field"><label for="f-emp">Mitarbeiter <span class="hint">z. B. 10–49</span></label><input id="f-emp" type="text" name="employees" value="<?= $v('employees') ?>" maxlength="30"></div>
    </div>
    <div class="grid-3">
      <div class="field"><label for="f-web">Website</label><input id="f-web" type="text" name="website" value="<?= $v('website') ?>" placeholder="www.beispiel.de" maxlength="300"></div>
      <div class="field"><label for="f-mail">E-Mail (öffentlich)</label><input id="f-mail" type="email" name="email" value="<?= $v('email') ?>" maxlength="150"></div>
      <div class="field"><label for="f-phone">Telefon</label><input id="f-phone" type="text" name="phone" value="<?= $v('phone') ?>" maxlength="50"></div>
    </div>
  </fieldset>
</div>

<?php if ($can('categories')): ?>
<div class="card">
  <h2>Kategorien <?= $mode === 'public' ? '*' : '' ?></h2>
  <p class="muted small">In welchen Bereichen ist der Anbieter tätig? Mehrfachauswahl möglich.</p>
  <div class="check-grid">
    <?php foreach (nav_categories() as $c): ?>
      <label class="check"><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selected, true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label>
    <?php endforeach ?>
  </div>
</div>
<?php elseif (!empty($provider['categories'])): ?>
<div class="card">
  <h2>Kategorien</h2>
  <div class="tags"><?php foreach ($provider['categories'] as $c): ?><span class="tag"><?= e($c['name']) ?></span><?php endforeach ?></div>
  <p class="muted small" style="margin:10px 0 0">Kategorien werden vom Betreiber zugeordnet.</p>
</div>
<?php endif ?>

<div class="card">
  <h2>Beschreibung</h2>
  <fieldset <?= $can('description') ? '' : 'disabled' ?>>
    <?= $can('description') ? '' : $locked ?>
    <div class="field"><label for="f-short">Kurzbeschreibung <?= $mode === 'public' ? '*' : '' ?> <span class="hint">max. 240 Zeichen, erscheint in Listen</span></label><input id="f-short" type="text" name="short_description" value="<?= $v('short_description') ?>" maxlength="240"></div>
    <div class="field"><label for="f-desc">Ausführliche Beschreibung <span class="hint">Leistungen, Spezialisierung, Referenzen – Absätze mit Leerzeile trennen</span></label><textarea id="f-desc" name="description" rows="9" maxlength="20000"><?= $v('description') ?></textarea></div>
  </fieldset>
</div>

<div class="card">
  <h2>Produkte &amp; Leistungen</h2>
  <?php if (!$can('products')): ?>
    <?= $locked ?>
    <?php foreach ($productList as $pr): ?><p><strong><?= e($pr['name'] ?? '') ?></strong> <span class="muted small"><?= e($pr['url'] ?? '') ?></span></p><?php endforeach ?>
  <?php else: ?>
    <p class="muted small">Konkrete Angebote mit Link zur jeweiligen Produktseite.</p>
    <div class="product-rows" data-product-rows>
      <?php foreach ($productList as $pr): ?><?= partial('product-row', ['pr' => $pr]) ?><?php endforeach ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" data-add-product>+ Weiteres Produkt</button>
    <template id="product-row-template"><?= partial('product-row', ['pr' => []]) ?></template>
  <?php endif ?>
</div>
