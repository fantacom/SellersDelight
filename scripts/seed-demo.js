'use strict';

// Legt fiktive Beispieldaten an, um die Oberfläche auszuprobieren. Nicht für den Produktivbetrieb.
const { bootstrap } = require('../src/bootstrap');
const { db } = require('../src/db');
const { categories, providers, reviews } = require('../src/models');

bootstrap();

if (db.prepare("SELECT COUNT(*) n FROM providers WHERE name LIKE 'Beispiel%'").get().n) {
  console.log('Demo-Daten sind bereits vorhanden.');
  process.exit(0);
}

const cat = (name) => categories.all().find((c) => c.name.startsWith(name))?.id;

const demo = [
  {
    name: 'Beispiel Produktfoto GmbH', legal_form: 'GmbH', street: 'Musterstraße 1', zip: '20095', city: 'Hamburg',
    founded_year: 2014, employees: '10–49', website: 'example.com', email: 'kontakt@example.com', phone: '+49 40 000000',
    short_description: 'Packshots, Lifestyle-Shootings und Infografiken für Amazon-Listings.',
    description: 'Fiktiver Demo-Eintrag.\n\nStudio mit Hohlkehle, Food- und Textilfotografie sowie 360°-Aufnahmen. Lieferung in Amazon-konformen Formaten.',
    cats: ['Fotostudio', 'Grafikagentur'],
    products: [['Packshot weiß', 'Freigestelltes Produktbild nach Amazon-Richtlinien', 'https://example.com/packshot', 'ab 29 € pro Bild'],
      ['Lifestyle-Shooting', 'Inszenierung mit Model oder in Szene', 'https://example.com/lifestyle', 'ab 590 € pro Tag']],
    ratings: [[5, 'Schnell und präzise', 'Bilder kamen nach drei Tagen, Freisteller sauber, Kommunikation sehr gut.'], [4, '', 'Gute Qualität, Lifestyle-Bilder etwas teurer als erwartet, aber den Preis wert.'], [5, 'Top', 'Unsere Conversion hat sich nach dem Bildtausch deutlich verbessert.']],
  },
  {
    name: 'Beispiel Ads Agentur', legal_form: 'UG', zip: '80331', city: 'München', founded_year: 2018, employees: '1–9',
    website: 'example.org', short_description: 'Amazon-PPC-Betreuung mit Fokus auf Profitabilität statt Umsatz.',
    description: 'Fiktiver Demo-Eintrag.\n\nKampagnenstruktur, Keyword-Harvesting und monatliches Reporting.',
    cats: ['PPC-Agentur'],
    products: [['PPC-Audit', 'Analyse des bestehenden Accounts', 'https://example.org/audit', '490 € einmalig']],
    ratings: [[4, 'Solide', 'ACoS in drei Monaten deutlich gesenkt. Reporting könnte übersichtlicher sein.'], [3, '', 'Ordentliche Arbeit, Reaktionszeiten aber teils langsam.']],
  },
  {
    name: 'Beispiel Steuerkanzlei', legal_form: 'PartG mbB', zip: '50667', city: 'Köln', founded_year: 2006, employees: '10–49',
    short_description: 'Steuerberatung für Online-Händler: OSS, Pan-EU, Buchhaltungsautomatisierung.',
    description: 'Fiktiver Demo-Eintrag.', cats: ['Steuerberater'], products: [],
    ratings: [[5, 'Kennt sich mit Amazon wirklich aus', 'Endlich eine Kanzlei, die Pan-EU-Lagerung versteht.']],
  },
  {
    name: 'Beispiel Fulfillment Lager', legal_form: 'GmbH', zip: '04109', city: 'Leipzig', founded_year: 2011, employees: '50–249',
    short_description: '3PL mit Prep-Service für FBA, Lagerung und Versand an Endkunden.',
    description: 'Fiktiver Demo-Eintrag.', cats: ['Third-Party'], products: [['FBA-Prep', 'Etikettierung, Polybag, Versand an Amazon', 'https://example.net/prep', 'ab 0,35 € pro Einheit']],
    ratings: [],
  },
];

for (const d of demo) {
  const pid = providers.save({
    ...d, category_ids: d.cats.map(cat).filter(Boolean),
    product_name: d.products.map((p) => p[0]), product_description: d.products.map((p) => p[1]),
    product_url: d.products.map((p) => p[2]), product_price: d.products.map((p) => p[3]),
  }, { sections: { master: true, description: true, categories: true, products: true }, status: 'published' });
  d.ratings.forEach(([rating, title, comment], i) => {
    const r = reviews.create({ providerId: pid, rating, title, comment, authorName: `Demo-Seller ${i + 1}`, authorEmail: `demo${i}@example.com` });
    reviews.moderate(Number(r.lastInsertRowid), 'approved', null);
  });
}
// Eine offene Bewertung für die Moderations-Ansicht
const first = providers.list({ sort: 'name' })[0];
reviews.create({ providerId: first.id, rating: 2, title: 'Wartet auf Freigabe', comment: 'Diese Demo-Bewertung liegt noch in der Moderation.', authorName: 'Demo-Prüfung', authorEmail: 'pending@example.com' });

console.log(`${demo.length} fiktive Demo-Anbieter angelegt.`);
