<?= partial('head', get_defined_vars()) ?>
<?php $labels = ['pending' => 'In Prüfung', 'published' => 'Online', 'rejected' => 'Abgelehnt']; ?>
<main>
  <div class="container narrow" style="padding:36px 20px 40px">
    <h1>Mein Konto</h1>
    <p class="muted">Angemeldet als <?= e($user['name']) ?> (<?= e($user['email']) ?>) · <a href="/konto/passwort">Passwort ändern</a><?php if ($user['role'] === 'admin'): ?> · <a href="/admin">Zum Backend</a><?php endif ?></p>
    <?= partial('flash', ['flash' => $flash]) ?>
    <div class="card">
      <h2>Meine Anbieter-Profile</h2>
      <?php if ($assignments): ?>
        <div class="table-wrap" style="border:0">
          <table>
            <thead><tr><th>Anbieter</th><th>Bearbeitbar</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($assignments as $a): ?>
              <tr>
                <td><strong><?= e($a['provider_name']) ?></strong><div class="sub"><span class="badge badge-<?= e($a['provider_status']) ?>"><?= $labels[$a['provider_status']] ?? '' ?></span><?php if ($a['pending_change']): ?> <span class="badge badge-pending">Änderung in Prüfung</span><?php endif ?></div></td>
                <td class="small"><?= e(implode(', ', array_filter([$a['can_edit_master'] ? 'Firmendaten' : '', $a['can_edit_description'] ? 'Beschreibung' : '', $a['can_edit_products'] ? 'Produkte' : ''])) ?: '–') ?></td>
                <td style="text-align:right" class="nowrap">
                  <?php if ($a['provider_status'] === 'published'): ?><a class="btn btn-ghost btn-sm" href="/anbieter/<?= e($a['provider_slug']) ?>">Ansehen</a><?php endif ?>
                  <a class="btn btn-sm" href="/konto/anbieter/<?= (int) $a['provider_id'] ?>">Bearbeiten</a>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Ihnen sind noch keine Anbieter-Profile zugewiesen.</p>
      <?php endif ?>
    </div>
  </div>
</main>
<?= partial('foot') ?>
