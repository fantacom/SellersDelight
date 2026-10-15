'use strict';

const express = require('express');
const bcrypt = require('bcryptjs');
const { requireLogin } = require('../auth');
const { providers, users, changes } = require('../models');
const { notifyAdmin } = require('../mail');
const { formToProvider } = require('./public');
const { MIN_PASSWORD } = require('./auth');

const router = express.Router();
router.use(requireLogin);

router.get('/', (req, res) => {
  const assignments = users.assignments(req.user.id).map((a) => ({
    ...a, pendingChange: !!changes.pendingFor(a.provider_id, req.user.id),
  }));
  res.render('account/index', { title: 'Mein Konto', assignments });
});

function loadAssignment(req, res, next) {
  const assignment = users.assignment(req.user.id, Number(req.params.id));
  const provider = assignment && providers.byId(assignment.provider_id);
  if (!provider) {
    return res.status(403).render('public/error', { title: 'Kein Zugriff', message: 'Sie haben keine Berechtigung, diesen Anbieter zu bearbeiten.' });
  }
  req.assignment = assignment;
  req.provider = provider;
  next();
}

function sectionsOf(a) {
  return { master: !!a.can_edit_master, description: !!a.can_edit_description, products: !!a.can_edit_products, categories: false };
}

/** Zeigt dem Bearbeiter seinen noch offenen Vorschlag statt des Live-Stands, damit nichts verloren geht. */
function editView(req, pending) {
  const base = { ...req.provider };
  if (pending) {
    const proposed = formToProvider(pending.data);
    for (const [section, keys] of Object.entries(SECTION_KEYS)) {
      if (pending.sections[section]) keys.forEach((k) => { base[k] = proposed[k]; });
    }
  }
  return base;
}

const SECTION_KEYS = {
  master: ['name', 'legal_form', 'street', 'zip', 'city', 'country', 'founded_year', 'employees', 'website', 'email', 'phone'],
  description: ['short_description', 'description'],
  products: ['products'],
};

router.get('/anbieter/:id', loadAssignment, (req, res) => {
  const pending = changes.pendingFor(req.provider.id, req.user.id);
  res.render('account/provider-edit', {
    title: req.provider.name,
    provider: editView(req, pending),
    sections: sectionsOf(req.assignment),
    pending,
    lastDecision: pending ? null : changes.lastDecidedFor(req.provider.id, req.user.id),
    errors: [],
  });
});

router.post('/anbieter/:id', loadAssignment, (req, res) => {
  const sections = sectionsOf(req.assignment);
  if (sections.master && !String(req.body.name || '').trim()) {
    return res.status(422).render('account/provider-edit', {
      title: req.provider.name,
      provider: { ...req.provider, ...formToProvider(req.body), categories: req.provider.categories },
      sections, pending: null, lastDecision: null, errors: ['Bitte geben Sie den Firmennamen an.'],
    });
  }
  const { _csrf, ...data } = req.body;
  changes.submit(req.provider.id, req.user.id, data, sections);
  notifyAdmin(`Änderung an „${req.provider.name}" wartet auf Freigabe`, `Eingereicht von ${req.user.name} <${req.user.email}>\n\nPrüfen: ${res.locals.baseUrl}/admin/aenderungen`);
  req.flash('success', 'Vielen Dank! Ihre Änderungen wurden zur Prüfung eingereicht und sind nach der Freigabe online.');
  res.redirect(`/konto/anbieter/${req.provider.id}`);
});

router.get('/passwort', (req, res) => {
  res.render('account/password', { title: 'Passwort ändern', error: null });
});

router.post('/passwort', (req, res) => {
  const fail = (error) => res.status(422).render('account/password', { title: 'Passwort ändern', error });
  const pw = String(req.body.password || '');
  if (!bcrypt.compareSync(String(req.body.current || ''), req.user.password_hash || '')) return fail('Das aktuelle Passwort ist falsch.');
  if (pw.length < MIN_PASSWORD) return fail(`Das neue Passwort muss mindestens ${MIN_PASSWORD} Zeichen lang sein.`);
  if (pw !== req.body.password_confirm) return fail('Die Passwörter stimmen nicht überein.');
  users.setPassword(req.user.id, bcrypt.hashSync(pw, 12));
  req.flash('success', 'Passwort geändert.');
  res.redirect(req.user.role === 'admin' ? '/admin' : '/konto');
});

module.exports = router;
