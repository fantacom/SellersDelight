<?= partial('admin-start', get_defined_vars()) ?>
<h1>Profil-Änderungen</h1>
<p class="muted">Änderungen, die Anbieter bzw. externe Bearbeiter an ihren Profilen vorgenommen haben. Sie werden erst nach Freigabe übernommen.</p>
<div class="tabs">
  <a href="?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">Zur Prüfung (<?= $pending['changes'] ?>)</a>
  <a href="?status=approved" class="<?= $status === 'approved' ? 'active' : '' ?>">Übernommen</a>
  <a href="?status=rejected" class="<?= $status === 'rejected' ? 'active' : '' ?>">Abgelehnt</a>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Anbieter</th><th>Eingereicht von</th><th>Datum</th><th>Bereiche</th><th></th></tr></thead>
    <tbody>
    <?php if (!$changes): ?><tr><td colspan="5" class="muted">Keine Einträge.</td></tr><?php endif ?>
    <?php foreach ($changes as $c): $sec = $c['sections']; ?>
      <tr>
        <td><strong><?= e($c['provider_name']) ?></strong></td>
        <td><?= e($c['user_name'] ?: '–') ?><div class="sub"><?= e($c['user_email'] ?? '') ?></div></td>
        <td class="small nowrap"><?= format_date($c['created_at']) ?></td>
        <td class="small"><?= e(implode(', ', array_filter([!empty($sec['master']) ? 'Firmendaten' : '', !empty($sec['description']) ? 'Beschreibung' : '', !empty($sec['products']) ? 'Produkte' : '']))) ?></td>
        <td style="text-align:right"><a class="btn <?= $c['status'] === 'pending' ? '' : 'btn-ghost' ?> btn-sm" href="/admin/aenderungen/<?= (int) $c['id'] ?>"><?= $c['status'] === 'pending' ? 'Prüfen' : 'Ansehen' ?></a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?= partial('admin-end') ?>
