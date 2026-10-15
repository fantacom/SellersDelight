<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="auth-wrap">
    <div class="logo"><img src="/img/logo.svg" alt="Seller's Delight"></div>
    <div class="card">
      <?php if (!$invited): ?>
        <h1 style="font-size:1.4rem">Link ungültig</h1>
        <p class="muted">Dieser Einladungslink ist abgelaufen oder wurde bereits verwendet. Bitte fordern Sie beim Betreiber einen neuen Link an.</p>
        <a class="btn btn-secondary" href="/login">Zur Anmeldung</a>
      <?php else: ?>
        <h1 style="font-size:1.4rem">Willkommen, <?= e($invited['name']) ?></h1>
        <p class="muted small">Legen Sie ein Passwort für <strong><?= e($invited['email']) ?></strong> fest.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="field"><label for="pw">Passwort <span class="hint">mind. <?= MIN_PASSWORD ?> Zeichen</span></label><input id="pw" type="password" name="password" required minlength="<?= MIN_PASSWORD ?>" autocomplete="new-password"></div>
          <div class="field"><label for="pw2">Passwort wiederholen</label><input id="pw2" type="password" name="password_confirm" required autocomplete="new-password"></div>
          <button class="btn" type="submit" style="width:100%">Zugang aktivieren</button>
        </form>
      <?php endif ?>
    </div>
  </div>
</main>
<?= partial('foot') ?>
