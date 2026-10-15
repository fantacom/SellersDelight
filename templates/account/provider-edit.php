<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="container narrow" style="padding:36px 20px 40px">
    <div class="breadcrumb"><a href="/konto">Mein Konto</a> / <?= e($provider['name']) ?></div>
    <h1><?= e($provider['name']) ?> bearbeiten</h1>
    <p class="muted">Änderungen werden vor der Veröffentlichung von uns geprüft.<?php if ($provider['status'] === 'published'): ?> <a href="/anbieter/<?= e($provider['slug']) ?>" target="_blank">Öffentliches Profil ansehen ↗</a><?php endif ?></p>
    <?= partial('flash', ['flash' => $flash]) ?>
    <?= partial('errors', ['errors' => $errors]) ?>
    <?php if ($pending): ?>
      <div class="alert alert-info">Ihre Änderungen vom <?= format_date($pending['created_at']) ?> warten auf Freigabe. Das Formular zeigt Ihren eingereichten Stand; erneutes Einreichen ersetzt den Vorschlag.</div>
    <?php elseif ($lastDecision && $lastDecision['status'] === 'rejected'): ?>
      <div class="alert alert-error">Ihre letzten Änderungen (<?= format_date($lastDecision['reviewed_at']) ?>) wurden nicht übernommen.<?php if ($lastDecision['note']): ?> Anmerkung: <?= e($lastDecision['note']) ?><?php endif ?></div>
    <?php endif ?>
    <form method="post">
      <?= partial('provider-form', ['provider' => $provider, 'sections' => $sections, 'mode' => 'editor']) ?>
      <div class="sticky-actions"><div class="btn-row"><button class="btn" type="submit">Zur Prüfung einreichen</button><a class="btn btn-ghost" href="/konto">Abbrechen</a></div></div>
    </form>
  </div>
</main>
<?= partial('foot') ?>
