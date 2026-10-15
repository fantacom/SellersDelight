<?php if (!empty($flash)): ?>
<div class="alerts">
  <?php foreach ($flash as $f): ?><div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach ?>
</div>
<?php endif ?>
