<?= partial('admin-start', get_defined_vars()) ?>
<?php $uid = (int) $editUser['id']; $allPerms = ['can_edit_master' => 1, 'can_edit_description' => 1, 'can_edit_products' => 1]; ?>
<div class="breadcrumb"><a href="/admin/benutzer">Benutzer</a> / <?= e($editUser['name']) ?></div>
<h1><?= e($editUser['name']) ?></h1>

<div class="card">
  <form method="post" action="/admin/benutzer/<?= $uid ?>">
    <?= csrf_field() ?>
    <div class="grid-3">
      <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($editUser['name']) ?>" required maxlength="100"></div>
      <div class="field"><label>E-Mail</label><input type="email" value="<?= e($editUser['email']) ?>" disabled></div>
      <div class="field"><label>Rolle</label><select name="role"><option value="editor" <?= $editUser['role'] === 'editor' ? 'selected' : '' ?>>Bearbeiter</option><option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="active" value="1" <?= $editUser['active'] ? 'checked' : '' ?>> Zugang aktiv</label></div>
    <button class="btn">Speichern</button>
  </form>
</div>

<div class="card">
  <h3>Zugangslink</h3>
  <p class="muted small"><?= $editUser['password_hash'] ? 'Der Benutzer hat ein Passwort. Ein neuer Link ermöglicht das Zurücksetzen.' : 'Der Benutzer hat seinen Zugang noch nicht aktiviert.' ?>
    <?php if ($editUser['invite_expires'] && $editUser['invite_expires'] > time()): ?> Offener Link gültig bis <?= date('d.m.Y', (int) $editUser['invite_expires']) ?>.<?php endif ?></p>
  <form method="post" action="/admin/benutzer/<?= $uid ?>/einladen"><?= csrf_field() ?><button class="btn btn-secondary"><?= $editUser['password_hash'] ? 'Link zum Zurücksetzen senden' : 'Einladung (erneut) senden' ?></button></form>
</div>

<?php if ($editUser['role'] === 'editor'): ?>
<div class="card">
  <h3>Zugewiesene Anbieter &amp; Rechte</h3>
  <?php if ($assignments): ?>
  <div class="table-wrap" style="margin-bottom:18px">
    <table>
      <thead><tr><th>Anbieter</th><th>Darf bearbeiten</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><a href="/admin/anbieter/<?= (int) $a['provider_id'] ?>"><strong><?= e($a['provider_name']) ?></strong></a></td>
          <td>
            <form method="post" action="/admin/benutzer/<?= $uid ?>/zuweisen" data-autosubmit class="perm-checks">
              <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $a['provider_id'] ?>">
              <?= partial('perm-checks', ['a' => $a]) ?>
            </form>
          </td>
          <td style="text-align:right"><form class="inline-form" method="post" action="/admin/benutzer/<?= $uid ?>/zuweisung/<?= (int) $a['provider_id'] ?>/entfernen"><?= csrf_field() ?><button class="btn btn-danger btn-sm">Entfernen</button></form></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?><p class="muted small">Noch keine Anbieter zugewiesen.</p><?php endif ?>
  <form method="post" action="/admin/benutzer/<?= $uid ?>/zuweisen">
    <?= csrf_field() ?>
    <div class="field"><label>Anbieter zuweisen</label>
      <select name="provider_id" required><option value="">– auswählen –</option><?php foreach ($allProviders as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?><?= $p['status'] !== 'published' ? ' (nicht öffentlich)' : '' ?></option><?php endforeach ?></select>
    </div>
    <div class="perm-checks field"><?= partial('perm-checks', ['a' => $allPerms]) ?></div>
    <button class="btn btn-secondary">Zuweisen</button>
  </form>
</div>
<?php endif ?>

<?php if ($uid !== (int) $user['id']): ?>
<div class="card">
  <form method="post" action="/admin/benutzer/<?= $uid ?>/loeschen" data-confirm="Benutzer „<?= e($editUser['name']) ?>“ löschen?"><?= csrf_field() ?><button class="btn btn-danger">Benutzer löschen</button></form>
</div>
<?php endif ?>
<?= partial('admin-end') ?>
