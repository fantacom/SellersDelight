<?= partial('admin-start', get_defined_vars()) ?>
<?php $icons = ['camera','pen','target','calculator','warehouse','scale','globe','chart','box','truck','megaphone','users','code','shield','cart','video','search','tag','calendar']; ?>
<h1>Kategorien</h1>
<p class="muted">Neue Rubriken erscheinen sofort auf der Startseite. Die Reihenfolge bestimmt die Sortierung (kleine Zahl = weiter vorne).</p>

<div class="card">
  <h3>Neue Kategorie</h3>
  <form method="post" action="/admin/kategorien" class="cat-edit">
    <?= csrf_field() ?>
    <div class="field"><label>Name</label><input type="text" name="name" required maxlength="80"></div>
    <div class="field"><label>Beschreibung</label><input type="text" name="description" maxlength="300"></div>
    <div class="field"><label>Symbol</label><select name="icon"><?php foreach ($icons as $i): ?><option><?= $i ?></option><?php endforeach ?></select></div>
    <div class="field"><label>Reihenf.</label><input type="number" name="sort_order" value="<?= (count($categories) + 1) * 10 ?>"></div>
    <button class="btn">Anlegen</button>
  </form>
</div>

<?php foreach ($categories as $c): ?>
<div class="card">
  <form method="post" action="/admin/kategorien/<?= (int) $c['id'] ?>" class="cat-edit">
    <?= csrf_field() ?>
    <div class="field"><label><span style="display:inline-flex;vertical-align:-4px;width:18px;height:18px;color:var(--blue-600)"><?= partial('icon', ['name' => $c['icon']]) ?></span> Name <span class="hint">(<?= (int) $c['provider_count'] ?> Anbieter)</span></label><input type="text" name="name" value="<?= e($c['name']) ?>" required maxlength="80"></div>
    <div class="field"><label>Beschreibung</label><input type="text" name="description" value="<?= e($c['description']) ?>" maxlength="300"></div>
    <div class="field"><label>Symbol</label><select name="icon"><?php foreach ($icons as $i): ?><option <?= $i === $c['icon'] ? 'selected' : '' ?>><?= $i ?></option><?php endforeach ?></select></div>
    <div class="field"><label>Reihenf.</label><input type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>"></div>
    <div class="btn-row"><button class="btn btn-secondary">Speichern</button></div>
  </form>
  <form method="post" action="/admin/kategorien/<?= (int) $c['id'] ?>/loeschen" data-confirm="Kategorie „<?= e($c['name']) ?>“ löschen? Anbieter bleiben erhalten." style="margin-top:8px"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Kategorie löschen</button> <a class="small" href="/kategorie/<?= e($c['slug']) ?>" target="_blank">/kategorie/<?= e($c['slug']) ?></a></form>
</div>
<?php endforeach ?>
<?= partial('admin-end') ?>
