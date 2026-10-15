<?= partial('admin-start', get_defined_vars()) ?>
<?php
$labels = ['pending' => 'Zur Prüfung', 'approved' => 'Übernommen', 'rejected' => 'Abgelehnt'];
$changedCount = count(array_filter($diff['rows'], fn($r) => $r['changed'])) + (!empty($diff['products']['changed']) ? 1 : 0);
?>
<div class="breadcrumb"><a href="/admin/aenderungen">Profil-Änderungen</a> / <?= e($provider['name']) ?></div>
<div class="section-head">
  <h1 style="margin:0">Änderung an <?= e($provider['name']) ?></h1>
  <span class="badge badge-<?= e($change['status']) ?>"><?= $labels[$change['status']] ?></span>
</div>
<p class="muted">Eingereicht von <strong><?= e($change['user_name'] ?: '–') ?></strong> <?= $change['user_email'] ? '(' . e($change['user_email']) . ')' : '' ?> am <?= format_date($change['created_at']) ?>.
  Verglichen mit dem aktuellen Live-Stand; geänderte Felder sind markiert.</p>
<?php if ($change['note']): ?><div class="alert alert-info">Anmerkung: <?= e($change['note']) ?></div><?php endif ?>
<?php if (!$changedCount): ?><div class="alert alert-info">Keine inhaltlichen Unterschiede zum aktuellen Stand.</div><?php endif ?>

<?php if ($diff['rows']): ?>
<div class="table-wrap" style="margin-bottom:20px">
  <table class="diff">
    <thead><tr><th style="width:18%">Feld</th><th style="width:41%">Aktuell</th><th>Vorschlag</th></tr></thead>
    <tbody>
    <?php foreach ($diff['rows'] as $row): ?>
      <tr class="<?= $row['changed'] ? 'changed' : 'same' ?>">
        <td><strong><?= e($row['label']) ?></strong></td>
        <td class="before"><?= e($row['before'] !== '' ? $row['before'] : '–') ?></td>
        <td class="after"><?= e($row['after'] !== '' ? $row['after'] : '–') ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>

<?php if ($diff['products']): $dp = $diff['products']; ?>
<div class="card <?= $dp['changed'] ? 'changed-card' : '' ?>">
  <h3>Produkte &amp; Leistungen <?= $dp['changed'] ? '' : '(unverändert)' ?></h3>
  <div class="grid-2">
    <?php foreach (['before' => 'Aktuell', 'after' => 'Vorschlag'] as $k => $label): ?>
      <div><div class="small muted"><?= $label ?></div><ul class="small"><?php foreach ($dp[$k] ?: ['–'] as $line): ?><li><?= e($line) ?></li><?php endforeach ?></ul></div>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<?php if ($change['status'] === 'pending'): ?>
<div class="card">
  <h3>Entscheidung</h3>
  <form method="post" action="/admin/aenderungen/<?= (int) $change['id'] ?>/freigeben">
    <?= csrf_field() ?>
    <div class="field"><label for="note">Anmerkung an den Bearbeiter <span class="hint">optional, wird per E-Mail mitgeschickt</span></label><input id="note" type="text" name="note" maxlength="1000"></div>
    <div class="btn-row">
      <button class="btn btn-success" type="submit">Übernehmen &amp; veröffentlichen</button>
      <button class="btn btn-secondary" type="submit" formaction="/admin/aenderungen/<?= (int) $change['id'] ?>/ablehnen">Ablehnen</button>
    </div>
  </form>
</div>
<?php endif ?>
<?= partial('admin-end') ?>
