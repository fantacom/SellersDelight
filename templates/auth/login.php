<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="auth-wrap">
    <div class="logo"><img src="/img/logo.svg" alt="Seller's Delight"></div>
    <div class="card">
      <h1 style="font-size:1.4rem">Anmelden</h1>
      <p class="muted small">Für Betreiber und Anbieter mit eigenem Zugang.</p>
      <?= partial('flash', ['flash' => $flash]) ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
      <form method="post" action="/login">
        <?= csrf_field() ?>
        <div class="field"><label for="email">E-Mail</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus></div>
        <div class="field"><label for="password">Passwort</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn" type="submit" style="width:100%">Anmelden</button>
      </form>
      <p class="small muted" style="margin:16px 0 0">Passwort vergessen? Bitte wenden Sie sich an den Betreiber – Sie erhalten einen neuen Zugangslink.</p>
    </div>
  </div>
</main>
<?= partial('foot') ?>
