<?= partial('admin-start', get_defined_vars()) ?>
<?php $labels = ['pending' => 'Zur Prüfung', 'published' => 'Online', 'rejected' => 'Abgelehnt']; ?>
<div class="section-head"><h1 style="margin:0">Anbieter</h1><a class="btn" href="/admin/anbieter/neu">+ Neuer Anbieter</a></div>
<div class="tabs">
  <a href="?" class="<?= !$status ? 'active' : '' ?>">Alle</a>
  <a href="?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">Zur Prüfung (<?= $counts['pending'] ?>)</a>
  <a href="?status=published" class="<?= $status === 'published' ? 'active' : '' ?>">Veröffentlicht (<?= $counts['published'] ?>)</a>
  <a href="?status=rejected" class="<?= $status === 'rejected' ? 'active' : '' ?>">Abgelehnt (<?= $counts['rejected'] ?>)</a>
</div>
<div class="toolbar">
  <form method="get"><input type="hidden" name="status" value="<?= e($status) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, Ort, Beschreibung …"><button class="btn btn-secondary">Suchen</button></form>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Anbieter</th><th>Kategorien</th><th>Bewertung</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$providers): ?><tr><td colspan="5" class="muted">Keine Einträge.</td></tr><?php endif ?>
    <?php foreach ($providers as $p): $sa = '/admin/anbieter/' . (int) $p['id'] . '/status'; ?>
      <tr>
        <td><a href="/admin/anbieter/<?= (int) $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><div class="sub"><?= e($p['city']) ?><?php if ($p['submitted_by_email']): ?> · eingereicht von <?= e($p['submitted_by_name']) ?> (<?= e($p['submitted_by_email']) ?>)<?php endif ?></div></td>
        <td class="small"><?= e(implode(', ', array_column($p['categories'], 'name')) ?: '–') ?></td>
        <td class="nowrap small"><?= format_rating($p['avg_rating']) ?> ★ <span class="muted">(<?= $p['review_count'] ?>)</span></td>
        <td><span class="badge badge-<?= e($p['status']) ?>"><?= $labels[$p['status']] ?></span></td>
        <td class="nowrap" style="text-align:right">
          <?php if ($p['status'] === 'pending'): ?>
            <form class="inline-form" method="post" action="<?= $sa ?>"><?= csrf_field() ?><input type="hidden" name="status" value="published"><button class="btn btn-success btn-sm">Freigeben</button></form>
            <form class="inline-form" method="post" action="<?= $sa ?>"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-secondary btn-sm">Ablehnen</button></form>
          <?php endif ?>
          <a class="btn btn-ghost btn-sm" href="/admin/anbieter/<?= (int) $p['id'] ?>">Bearbeiten</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?= partial('admin-end') ?>
