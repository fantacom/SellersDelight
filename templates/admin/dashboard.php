<?= partial('admin-start', get_defined_vars()) ?>
<h1>Übersicht</h1>
<div class="kpis">
  <a class="kpi <?= $reviewCounts['pending'] ? 'warn' : '' ?>" href="/admin/bewertungen"><div class="label">Bewertungen zur Prüfung</div><div class="value"><?= $reviewCounts['pending'] ?></div></a>
  <a class="kpi <?= $providerCounts['pending'] ? 'warn' : '' ?>" href="/admin/anbieter?status=pending"><div class="label">Neue Anbieter zur Prüfung</div><div class="value"><?= $providerCounts['pending'] ?></div></a>
  <a class="kpi <?= $pending['changes'] ? 'warn' : '' ?>" href="/admin/aenderungen"><div class="label">Profil-Änderungen zur Prüfung</div><div class="value"><?= $pending['changes'] ?></div></a>
  <a class="kpi" href="/admin/anbieter?status=published"><div class="label">Veröffentlichte Anbieter</div><div class="value"><?= $providerCounts['published'] ?></div></a>
  <a class="kpi" href="/admin/bewertungen?status=approved"><div class="label">Freigegebene Bewertungen</div><div class="value"><?= $reviewCounts['approved'] ?></div></a>
  <a class="kpi" href="/admin/kategorien"><div class="label">Kategorien</div><div class="value"><?= $categoryCount ?></div></a>
  <a class="kpi" href="/admin/benutzer"><div class="label">Benutzer</div><div class="value"><?= $userCount ?></div></a>
</div>

<div class="grid-2" style="gap:20px;align-items:start">
  <div class="card">
    <div class="section-head" style="margin-bottom:10px"><h3 style="margin:0">Neue Bewertungen</h3><a class="small" href="/admin/bewertungen">Alle →</a></div>
    <?php if ($pendingReviews): foreach ($pendingReviews as $rv): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--slate-100)">
        <?= partial('stars', ['value' => $rv['rating'], 'size' => 'sm']) ?> <strong class="small"><?= e($rv['provider_name']) ?></strong>
        <div class="small muted"><?= e(mb_strimwidth($rv['comment'], 0, 115, ' …')) ?></div>
      </div>
    <?php endforeach; else: ?><p class="muted small">Keine offenen Bewertungen.</p><?php endif ?>
  </div>
  <div class="card">
    <div class="section-head" style="margin-bottom:10px"><h3 style="margin:0">Neue Anbieter-Einträge</h3><a class="small" href="/admin/anbieter?status=pending">Alle →</a></div>
    <?php if ($pendingProviders): foreach ($pendingProviders as $p): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--slate-100)">
        <a href="/admin/anbieter/<?= (int) $p['id'] ?>"><strong class="small"><?= e($p['name']) ?></strong></a>
        <div class="small muted"><?= e(implode(', ', array_column($p['categories'], 'name'))) ?> · eingereicht von <?= e($p['submitted_by_name'] ?: '–') ?></div>
      </div>
    <?php endforeach; else: ?><p class="muted small">Keine offenen Einträge.</p><?php endif ?>
  </div>
</div>

<?php if ($pendingChanges): ?>
<div class="card" style="margin-top:20px">
  <div class="section-head" style="margin-bottom:10px"><h3 style="margin:0">Profil-Änderungen von Anbietern</h3><a class="small" href="/admin/aenderungen">Alle →</a></div>
  <?php foreach ($pendingChanges as $c): ?>
    <div style="padding:10px 0;border-bottom:1px solid var(--slate-100)">
      <a href="/admin/aenderungen/<?= (int) $c['id'] ?>"><strong class="small"><?= e($c['provider_name']) ?></strong></a>
      <div class="small muted">von <?= e($c['user_name'] ?: 'gelöschter Benutzer') ?> · <?= format_date($c['created_at']) ?></div>
    </div>
  <?php endforeach ?>
</div>
<?php endif ?>
<?= partial('admin-end') ?>
