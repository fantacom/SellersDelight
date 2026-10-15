<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="container narrow">
    <div class="page-head">
      <div class="breadcrumb"><a href="/">Start</a> / Anbieter eintragen</div>
      <h1>Anbieter eintragen</h1>
      <p class="muted">Sie kennen einen Dienstleister, der hier noch fehlt? Tragen Sie ihn ein. Wir prüfen die Angaben und schalten den Eintrag anschließend frei. Pflichtfelder sind mit * markiert.</p>
    </div>
    <?= partial('errors', ['errors' => $errors]) ?>
    <form method="post" action="/anbieter-eintragen" novalidate style="margin-bottom:40px">
      <div class="hp" aria-hidden="true"><label>Homepage <input type="text" name="homepage" tabindex="-1" autocomplete="off"></label></div>
      <?= partial('provider-form', ['provider' => $provider, 'sections' => ['master' => true, 'description' => true, 'categories' => true, 'products' => true], 'mode' => 'public']) ?>
      <div class="card">
        <h2>Ihre Angaben</h2>
        <p class="muted small">Nur für Rückfragen – wird nicht veröffentlicht.</p>
        <div class="grid-2">
          <div class="field"><label for="s-name">Ihr Name *</label><input id="s-name" type="text" name="submitted_by_name" value="<?= e($provider['submitted_by_name'] ?? '') ?>" maxlength="100"></div>
          <div class="field"><label for="s-mail">Ihre E-Mail *</label><input id="s-mail" type="email" name="submitted_by_email" value="<?= e($provider['submitted_by_email'] ?? '') ?>" maxlength="150"></div>
        </div>
        <div class="field"><label class="check"><input type="checkbox" name="consent" value="1" <?= !empty($provider['consent']) ? 'checked' : '' ?>> <span>Ich habe die <a href="/datenschutz" target="_blank">Datenschutzhinweise</a> gelesen.</span></label></div>
        <button class="btn btn-lg" type="submit">Eintrag zur Prüfung senden</button>
      </div>
    </form>
  </div>
</main>
<?= partial('foot') ?>
