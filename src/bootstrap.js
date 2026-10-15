'use strict';

const bcrypt = require('bcryptjs');
const { db } = require('./db');
const { categories, users } = require('./models');
const { token } = require('./util');

const DEFAULT_CATEGORIES = [
  ['Fotostudio', 'camera', 'Produktfotos, Lifestyle-Shootings und 360°-Aufnahmen für Listings und Shops.'],
  ['Grafikagentur', 'pen', 'Listing-Grafiken, A+ Content, Brand Stores, Verpackung und Corporate Design.'],
  ['PPC-Agentur', 'target', 'Betreuung von Amazon Ads, Google Shopping und weiteren Marktplatz-Kampagnen.'],
  ['Steuerberater', 'calculator', 'Steuerberatung mit E-Commerce-Fokus: OSS, Umsatzsteuer im Ausland, Buchhaltung.'],
  ['Third-Party-Lager (3PL)', 'warehouse', 'Fulfillment, Lagerung, Prep-Center und Versanddienstleister.'],
  ['Rechtsberatung', 'scale', 'Markenrecht, Abmahnschutz, AGB und Produktkonformität.'],
  ['Übersetzung & Lokalisierung', 'globe', 'Listing-Übersetzungen und Keyword-Lokalisierung für internationale Marktplätze.'],
  ['Software & Tools', 'chart', 'Analyse-, Repricing-, Buchhaltungs- und Warenwirtschafts-Tools.'],
];

function bootstrap() {
  if (db.prepare('SELECT COUNT(*) n FROM categories').get().n === 0) {
    DEFAULT_CATEGORIES.forEach(([name, icon, description], i) =>
      categories.save({ name, icon, description, sort_order: (i + 1) * 10 }));
    console.log(`[setup] ${DEFAULT_CATEGORIES.length} Standard-Kategorien angelegt.`);
  }

  if (db.prepare('SELECT COUNT(*) n FROM users').get().n === 0) {
    const email = process.env.ADMIN_EMAIL || 'admin@sellersdelight.local';
    const password = process.env.ADMIN_PASSWORD || token(12);
    const id = users.create({ email, name: 'Administrator', role: 'admin' });
    users.setPassword(id, bcrypt.hashSync(password, 12));
    console.log('[setup] Administrator angelegt:');
    console.log(`        E-Mail:   ${email}`);
    if (!process.env.ADMIN_PASSWORD) console.log(`        Passwort: ${password}  (bitte nach dem ersten Login ändern)`);
  }
}

module.exports = { bootstrap };
