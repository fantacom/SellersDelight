<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="container narrow" style="padding:72px 20px;text-align:center">
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-secondary" href="/">Zur Startseite</a>
  </div>
</main>
<?= partial('foot') ?>
