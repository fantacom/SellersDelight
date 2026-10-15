<footer class="site-footer">
  <div class="container">
    <div class="cols">
      <div>
        <div class="brand"><span style="width:30px;height:30px;display:block"><?= partial('logo-mark', ['uid' => 'f']) ?></span><span>Seller's <span class="accent">Delight</span></span></div>
        <p>Unabhängige Bewertungen von Dienstleistern für Amazon- und E-Commerce-Seller. Jede Bewertung wird vor der Veröffentlichung geprüft.</p>
      </div>
      <div>
        <h4>Kategorien</h4>
        <ul><?php foreach (array_slice(nav_categories(), 0, 6) as $c): ?><li><a href="/kategorie/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a></li><?php endforeach ?></ul>
      </div>
      <div>
        <h4>Seller's Delight</h4>
        <ul>
          <li><a href="/anbieter-eintragen">Anbieter eintragen</a></li>
          <li><a href="/login">Anmelden</a></li>
          <li><a href="/impressum">Impressum</a></li>
          <li><a href="/datenschutz">Datenschutz</a></li>
        </ul>
      </div>
    </div>
    <div class="bottom">© <?= date('Y') ?> Seller's Delight</div>
  </div>
</footer>
</body>
</html>
