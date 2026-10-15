<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="auth-wrap">
    <div class="card">
      <h1 style="font-size:1.4rem">Passwort ändern</h1>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="cur">Aktuelles Passwort</label><input id="cur" type="password" name="current" required autocomplete="current-password"></div>
        <div class="field"><label for="pw">Neues Passwort <span class="hint">mind. <?= MIN_PASSWORD ?> Zeichen</span></label><input id="pw" type="password" name="password" required minlength="<?= MIN_PASSWORD ?>" autocomplete="new-password"></div>
        <div class="field"><label for="pw2">Neues Passwort wiederholen</label><input id="pw2" type="password" name="password_confirm" required autocomplete="new-password"></div>
        <div class="btn-row"><button class="btn" type="submit">Speichern</button><a class="btn btn-ghost" href="<?= home_for($user) ?>">Abbrechen</a></div>
      </form>
    </div>
  </div>
</main>
<?= partial('foot') ?>
