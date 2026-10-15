<a class="provider-row" href="/anbieter/<?= e($p['slug']) ?>">
  <div class="rank <?= $rank <= 3 && $p['review_count'] ? 'top' : '' ?>"><?= $rank ?></div>
  <div class="avatar"><?= e(initials($p['name'])) ?></div>
  <div>
    <h3><?= e($p['name']) ?></h3>
    <p><?= e($p['short_description']) ?></p>
    <div class="provider-meta">
      <?php if ($p['city']): ?><span><?= e($p['city']) ?></span><?php endif ?>
      <?php if ($p['founded_year']): ?><span>Seit <?= (int) $p['founded_year'] ?></span><?php endif ?>
      <?php if (!empty($showCats)): foreach ($p['categories'] as $c): ?><span class="tag"><?= e($c['name']) ?></span><?php endforeach; endif ?>
    </div>
  </div>
  <div class="rating-box">
    <div class="score"><?= format_rating($p['avg_rating']) ?></div>
    <?= partial('stars', ['value' => $p['avg_rating']]) ?>
    <div class="count"><?= $p['review_count'] ?> <?= $p['review_count'] === 1 ? 'Bewertung' : 'Bewertungen' ?></div>
  </div>
</a>
