'use strict';

const express = require('express');
const bcrypt = require('bcryptjs');
const { requireLogin } = require('../auth');
const { providers, users } = require('../models');
const { formToProvider } = require('./public');
const { MIN_PASSWORD } = require('./auth');

const router = express.Router();
router.use(requireLogin);

router.get('/', (req, res) => {
  res.render('account/index', { title: 'Mein Konto', assignments: users.assignments(req.user.id) });
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

router.get('/anbieter/:id', loadAssignment, (req, res) => {
  res.render('account/provider-edit', {
    title: req.provider.name, provider: req.provider, sections: sectionsOf(req.assignment), errors: [],
  });
});

router.post('/anbieter/:id', loadAssignment, (req, res) => {
  const sections = sectionsOf(req.assignment);
  if (sections.master && !String(req.body.name || '').trim()) {
    return res.status(422).render('account/provider-edit', {
      title: req.provider.name,
      provider: { ...req.provider, ...formToProvider(req.body), categories: req.provider.categories },
      sections, errors: ['Bitte geben Sie den Firmennamen an.'],
    });
  }
  providers.save(req.body, { id: req.provider.id, sections });
  req.flash('success', 'Änderungen gespeichert und sofort online.');
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
