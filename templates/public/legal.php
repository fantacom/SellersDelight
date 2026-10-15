<?= partial('head', get_defined_vars()) ?>
<main>
  <div class="container narrow" style="padding:40px 20px">
    <h1><?= e($title) ?></h1>
    <div class="card prose">
      <?php if ($page === 'impressum'): ?>
        <p><strong>Angaben gemäß § 5 DDG</strong></p>
        <p>[Firmenname]<br>[Straße Hausnummer]<br>[PLZ Ort]</p>
        <p>Vertreten durch: [Name]<br>E-Mail: [E-Mail]<br>Telefon: [Telefon]</p>
        <p>Registereintrag / USt-IdNr.: [ergänzen]</p>
      <?php else: ?>
        <p><strong>Platzhalter:</strong> Bitte hier die Datenschutzerklärung einfügen.</p>
        <p>Hinweise für die Erstellung: Beim Abgeben einer Bewertung bzw. beim Eintragen eines Anbieters werden Name und E-Mail-Adresse gespeichert. Der Name wird öffentlich angezeigt, die E-Mail-Adresse nicht; sie dient nur zur Missbrauchsprüfung und Rückfrage. Zur Missbrauchsabwehr wird die IP-Adresse kurzzeitig (max. 24 Stunden) gespeichert. Es werden keine externen Schriftarten, Tracking- oder Analysedienste eingebunden. Es wird ein technisch notwendiges Sitzungs-Cookie gesetzt. Hosting: IONOS SE.</p>
      <?php endif ?>
    </div>
  </div>
</main>
<?= partial('foot') ?>
