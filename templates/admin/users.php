<?= partial('admin-start', get_defined_vars()) ?>
<h1>Benutzer &amp; Zugänge</h1>
<p class="muted"><strong>Administratoren</strong> haben vollen Zugriff auf das Backend. <strong>Bearbeiter</strong> (z. B. Dienstleister) können nur die ihnen zugewiesenen Anbieter und Bereiche bearbeiten – Änderungen werden erst nach Freigabe übernommen.</p>

<div class="card">
  <h3>Neuen Benutzer anlegen</h3>
  <form method="post" action="/admin/benutzer">
    <?= csrf_field() ?>
    <div class="grid-3">
      <div class="field"><label>Name</label><input type="text" name="name" required maxlength="100"></div>
      <div class="field"><label>E-Mail</label><input type="email" name="email" required maxlength="150"></div>
      <div class="field"><label>Rolle</label><select name="role"><option value="editor">Bearbeiter</option><option value="admin">Administrator</option></select></div>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="send_invite" value="1" checked> Einladung mit Link zum Festlegen des Passworts senden</label></div>
    <button class="btn">Anlegen</button>
  </form>
</div>

<div class="table-wrap" style="margin-top:20px">
  <table>
    <thead><tr><th>Name</th><th>Rolle</th><th>Anbieter</th><th>Status</th><th>Letzter Login</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><a href="/admin/benutzer/<?= (int) $u['id'] ?>"><strong><?= e($u['name']) ?></strong></a><div class="sub"><?= e($u['email']) ?></div></td>
        <td><span class="badge badge-<?= e($u['role']) ?>"><?= $u['role'] === 'admin' ? 'Administrator' : 'Bearbeiter' ?></span></td>
        <td><?= $u['role'] === 'admin' ? 'alle' : (int) $u['provider_count'] ?></td>
        <td class="small"><?= !$u['active'] ? 'Deaktiviert' : ($u['password_hash'] ? 'Aktiv' : 'Einladung offen') ?></td>
        <td class="small"><?= $u['last_login_at'] ? format_date($u['last_login_at']) : '–' ?></td>
        <td style="text-align:right"><a class="btn btn-ghost btn-sm" href="/admin/benutzer/<?= (int) $u['id'] ?>">Bearbeiten</a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?= partial('admin-end') ?>
