<?= partial('admin-start', get_defined_vars()) ?>
<?php $isNew = empty($provider['id']); $allPerms = ['can_edit_master' => 1, 'can_edit_description' => 1, 'can_edit_products' => 1]; ?>
<div class="breadcrumb"><a href="/admin/anbieter">Anbieter</a> / <?= $isNew ? 'Neu' : e($provider['name']) ?></div>
<div class="section-head">
  <h1 style="margin:0"><?= $isNew ? 'Neuer Anbieter' : e($provider['name']) ?></h1>
  <?php if (!$isNew): ?><a class="btn btn-secondary btn-sm" href="/anbieter/<?= e($provider['slug']) ?>" target="_blank">Profil ansehen ↗</a><?php endif ?>
</div>
<?php if (!empty($provider['submitted_by_email'])): ?>
  <div class="alert alert-info">Eingereicht von <strong><?= e($provider['submitted_by_name']) ?></strong> (<a href="mailto:<?= e($provider['submitted_by_email']) ?>"><?= e($provider['submitted_by_email']) ?></a>)<?= !empty($provider['created_at']) ? ' am ' . format_date($provider['created_at']) : '' ?>.</div>
<?php endif ?>
<?= partial('errors', ['errors' => $errors]) ?>

<form method="post" action="<?= $isNew ? '/admin/anbieter/neu' : '/admin/anbieter/' . (int) $provider['id'] ?>">
  <div class="card">
    <div class="grid-2">
      <div class="field" style="margin:0">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (['published' => 'Veröffentlicht', 'pending' => 'Zur Prüfung (nicht öffentlich)', 'rejected' => 'Abgelehnt (nicht öffentlich)'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($provider['status'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <?php if (!$isNew): ?>
      <div class="field" style="margin:0"><label for="slug">URL-Kürzel <span class="hint">/anbieter/…</span></label><input id="slug" type="text" name="slug" value="<?= e($provider['slug']) ?>" maxlength="80"></div>
      <?php endif ?>
    </div>
  </div>
  <?= partial('provider-form', ['provider' => $provider, 'sections' => ALL_SECTIONS, 'mode' => 'admin']) ?>
  <div class="sticky-actions"><div class="btn-row"><button class="btn" type="submit">Speichern</button><a class="btn btn-ghost" href="/admin/anbieter">Zurück</a></div></div>
</form>

<?php if (!$isNew): $pid = (int) $provider['id']; ?>
<div class="card" id="zugaenge" style="margin-top:24px">
  <h2>Zugänge für Dritte</h2>
  <p class="muted small">Geben Sie z. B. dem Anbieter selbst Zugriff, damit er sein Profil pflegt. Sie legen fest, welche Bereiche bearbeitet werden dürfen. Änderungen werden erst nach Ihrer Freigabe übernommen. Kategorien und Status bleiben immer bei Ihnen.</p>
  <?php if ($editors): ?>
  <div class="table-wrap" style="margin-bottom:18px">
    <table>
      <thead><tr><th>Person</th><th>Darf bearbeiten</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($editors as $ed): ?>
        <tr>
          <td><a href="/admin/benutzer/<?= (int) $ed['user_id'] ?>"><strong><?= e($ed['name']) ?></strong></a><div class="sub"><?= e($ed['email']) ?></div></td>
          <td>
            <form method="post" action="/admin/benutzer/<?= (int) $ed['user_id'] ?>/zuweisen" data-autosubmit class="perm-checks">
              <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= $pid ?>">
              <?= partial('perm-checks', ['a' => $ed]) ?>
            </form>
          </td>
          <td style="text-align:right"><form class="inline-form" method="post" action="/admin/benutzer/<?= (int) $ed['user_id'] ?>/zuweisung/<?= $pid ?>/entfernen" data-confirm="Zugriff entfernen?"><?= csrf_field() ?><button class="btn btn-danger btn-sm">Entfernen</button></form></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
  <form method="post" action="/admin/anbieter/<?= $pid ?>/zugang">
    <?= csrf_field() ?>
    <div class="grid-2">
      <div class="field"><label for="z-mail">E-Mail</label><input id="z-mail" type="email" name="email" value="<?= e($provider['email'] ?? '') ?>" required></div>
      <div class="field"><label for="z-name">Name <span class="hint">Ansprechpartner</span></label><input id="z-name" type="text" name="name" placeholder="<?= e($provider['name']) ?>"></div>
    </div>
    <div class="perm-checks field"><?= partial('perm-checks', ['a' => $allPerms]) ?></div>
    <div class="field"><label class="check"><input type="checkbox" name="send_invite" value="1" checked> Einladung senden („Für Sie wurde ein Zugang angelegt …“)</label></div>
    <button class="btn btn-secondary" type="submit">Zugang anlegen / zuweisen</button>
  </form>
</div>

<div class="card" style="margin-top:24px">
  <h3>Anbieter löschen</h3>
  <p class="muted small">Löscht den Anbieter inklusive Produkten, Bewertungen und Zuweisungen endgültig.</p>
  <form method="post" action="/admin/anbieter/<?= $pid ?>/loeschen" data-confirm="„<?= e($provider['name']) ?>“ mit allen Bewertungen endgültig löschen?"><?= csrf_field() ?><button class="btn btn-danger">Endgültig löschen</button></form>
</div>
<?php endif ?>
<?= partial('admin-end') ?>
