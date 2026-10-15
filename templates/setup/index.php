<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="auth-wrap">
    <div class="logo"><img src="/img/logo.svg" alt="Seller's Delight"></div>
    <div class="card">
      <h1 style="font-size:1.4rem">Ersteinrichtung</h1>
      <p class="muted small">Die Datenbank ist bereit. Legen Sie jetzt Ihren Administrator-Zugang an. Diese Seite ist nur verfügbar, solange noch kein Benutzer existiert.</p>
      <?= partial('errors', ['errors' => $errors]) ?>
      <form method="post" action="/einrichtung">
        <?= csrf_field() ?>
        <div class="field"><label for="name">Ihr Name</label><input id="name" type="text" name="name" value="<?= e($form['name'] ?? '') ?>" required maxlength="100"></div>
        <div class="field"><label for="email">E-Mail</label><input id="email" type="email" name="email" value="<?= e($form['email'] ?? '') ?>" required autocomplete="username"></div>
        <div class="field"><label for="pw">Passwort <span class="hint">mind. <?= MIN_PASSWORD ?> Zeichen</span></label><input id="pw" type="password" name="password" required minlength="<?= MIN_PASSWORD ?>" autocomplete="new-password"></div>
        <div class="field"><label for="pw2">Passwort wiederholen</label><input id="pw2" type="password" name="password_confirm" required autocomplete="new-password"></div>
        <button class="btn" type="submit" style="width:100%">Administrator anlegen</button>
      </form>
    </div>
  </div>
</main>
<?= partial('foot') ?>
