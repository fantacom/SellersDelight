<?= partial('admin-start', get_defined_vars()) ?>
<?php $labels = ['pending' => 'Zur Prüfung', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt']; ?>
<h1>Bewertungen</h1>
<div class="tabs">
  <a href="?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">Zur Prüfung (<?= $counts['pending'] ?>)</a>
  <a href="?status=approved" class="<?= $status === 'approved' ? 'active' : '' ?>">Freigegeben (<?= $counts['approved'] ?>)</a>
  <a href="?status=rejected" class="<?= $status === 'rejected' ? 'active' : '' ?>">Abgelehnt (<?= $counts['rejected'] ?>)</a>
  <a href="?status=all" class="<?= $status === 'all' ? 'active' : '' ?>">Alle</a>
</div>
<?php if (!$reviews): ?><div class="card empty"><p>Keine Bewertungen in dieser Ansicht.</p></div><?php endif ?>
<?php foreach ($reviews as $rv): $base = '/admin/bewertungen/' . (int) $rv['id']; ?>
<div class="mod-card">
  <div class="head">
    <div>
      <?= partial('stars', ['value' => $rv['rating']]) ?>
      <strong style="margin-left:6px"><?= (int) $rv['rating'] ?>/5</strong> für
      <a href="/anbieter/<?= e($rv['provider_slug']) ?>" target="_blank"><strong><?= e($rv['provider_name']) ?></strong></a>
      <?php if ($rv['provider_status'] !== 'published'): ?><span class="badge badge-pending">Anbieter nicht öffentlich</span><?php endif ?>
    </div>
    <span class="badge badge-<?= e($rv['status']) ?>"><?= $labels[$rv['status']] ?></span>
  </div>
  <?php if ($rv['title']): ?><h4 style="margin:4px 0"><?= e($rv['title']) ?></h4><?php endif ?>
  <p><?= e($rv['comment']) ?></p>
  <div class="small muted" style="margin-bottom:12px"><?= e($rv['author_name']) ?> · <a href="mailto:<?= e($rv['author_email']) ?>"><?= e($rv['author_email']) ?></a> · <?= format_date($rv['created_at']) ?></div>
  <div class="btn-row">
    <?php if ($rv['status'] !== 'approved'): ?><form class="inline-form" method="post" action="<?= $base ?>/freigeben"><?= csrf_field() ?><button class="btn btn-success btn-sm">Freigeben</button></form><?php endif ?>
    <?php if ($rv['status'] !== 'rejected'): ?><form class="inline-form" method="post" action="<?= $base ?>/ablehnen"><?= csrf_field() ?><button class="btn btn-secondary btn-sm">Ablehnen</button></form><?php endif ?>
    <form class="inline-form" method="post" action="<?= $base ?>/loeschen" data-confirm="Bewertung endgültig löschen?"><?= csrf_field() ?><button class="btn btn-danger btn-sm">Löschen</button></form>
  </div>
</div>
<?php endforeach ?>
<?= partial('admin-end') ?>
