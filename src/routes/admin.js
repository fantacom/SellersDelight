'use strict';

const express = require('express');
const { requireAdmin } = require('../auth');
const { categories, providers, reviews, users, changes, normalizeInput } = require('../models');
const { sendMail } = require('../mail');
const { str, isEmail } = require('../util');
const { sendInvite, inviteFlash } = require('../invite');
const { formToProvider } = require('./public');

const router = express.Router();
router.use(requireAdmin);
router.use((req, res, next) => {
  res.locals.pending = {
    reviews: reviews.countByStatus().pending,
    providers: providers.countByStatus().pending,
    changes: changes.countPending(),
  };
  next();
});

const ALL_SECTIONS = { master: true, description: true, categories: true, products: true };
const STATUSES = ['pending', 'published', 'rejected'];
const back = (req, fallback) => {
  const ref = req.get('referer');
  return ref && new URL(ref).host === req.get('host') ? ref : fallback;
};
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

/* ---------- Dashboard ---------- */

router.get('/', (req, res) => {
  res.render('admin/dashboard', {
    title: 'Übersicht',
    reviewCounts: reviews.countByStatus(),
    providerCounts: providers.countByStatus(),
    pendingReviews: reviews.list('pending').slice(0, 5),
    pendingProviders: providers.list({ status: 'pending', sort: 'newest', limit: 5 }),
    pendingChanges: changes.list('pending').slice(0, 5),
    categoryCount: categories.all().length,
    userCount: users.all().length,
  });
});

/* ---------- Bewertungen ---------- */

router.get('/bewertungen', (req, res) => {
  const status = ['pending', 'approved', 'rejected', 'all'].includes(req.query.status) ? req.query.status : 'pending';
  res.render('admin/reviews', {
    title: 'Bewertungen',
    status,
    counts: reviews.countByStatus(),
    reviews: reviews.list(status === 'all' ? null : status),
  });
});

router.post('/bewertungen/:id/:action', (req, res) => {
  const map = { freigeben: 'approved', ablehnen: 'rejected', zuruecksetzen: 'pending' };
  if (req.params.action === 'loeschen') {
    reviews.remove(req.params.id);
    req.flash('success', 'Bewertung gelöscht.');
  } else if (map[req.params.action]) {
    reviews.moderate(req.params.id, map[req.params.action], req.user.id);
    req.flash('success', map[req.params.action] === 'approved' ? 'Bewertung freigegeben.' : 'Bewertung aktualisiert.');
  }
  res.redirect(back(req, '/admin/bewertungen'));
});

/* ---------- Anbieter ---------- */

router.get('/anbieter', (req, res) => {
  const status = STATUSES.includes(req.query.status) ? req.query.status : '';
  const q = str(req.query.q, 100);
  res.render('admin/providers', {
    title: 'Anbieter',
    status,
    q,
    counts: providers.countByStatus(),
    providers: providers.list({ status, search: q, sort: status === 'pending' ? 'newest' : 'name', limit: 1000 }),
  });
});

router.get('/anbieter/neu', (req, res) => {
  res.render('admin/provider-edit', {
    title: 'Neuer Anbieter', provider: { category_ids: [], products: [], status: 'published' }, errors: [], editors: [],
  });
});

function validateProvider(body) {
  const errors = [];
  if (!str(body.name)) errors.push('Bitte geben Sie den Firmennamen an.');
  return errors;
}

router.post('/anbieter/neu', (req, res) => {
  const errors = validateProvider(req.body);
  if (errors.length) {
    return res.status(422).render('admin/provider-edit', {
      title: 'Neuer Anbieter', provider: formToProvider(req.body), errors, editors: [],
    });
  }
  const status = STATUSES.includes(req.body.status) ? req.body.status : 'published';
  const id = providers.save(req.body, { sections: ALL_SECTIONS, status });
  req.flash('success', 'Anbieter angelegt.');
  res.redirect(`/admin/anbieter/${id}`);
});

router.get('/anbieter/:id', (req, res, next) => {
  const provider = providers.byId(req.params.id);
  if (!provider) return next();
  provider.category_ids = provider.categories.map((c) => c.id);
  res.render('admin/provider-edit', {
    title: provider.name, provider, errors: [], editors: users.editorsOf(provider.id),
  });
});

router.post('/anbieter/:id', (req, res, next) => {
  const existing = providers.byId(req.params.id);
  if (!existing) return next();
  const errors = validateProvider(req.body);
  if (errors.length) {
    return res.status(422).render('admin/provider-edit', {
      title: existing.name, provider: { ...formToProvider(req.body), id: existing.id, slug: existing.slug },
      errors, editors: users.editorsOf(existing.id),
    });
  }
  const status = STATUSES.includes(req.body.status) ? req.body.status : existing.status;
  providers.save(req.body, { id: existing.id, sections: ALL_SECTIONS, status });
  req.flash('success', 'Änderungen gespeichert.');
  res.redirect(`/admin/anbieter/${existing.id}`);
});

router.post('/anbieter/:id/status', (req, res) => {
  if (STATUSES.includes(req.body.status)) {
    providers.setStatus(req.params.id, req.body.status);
    req.flash('success', req.body.status === 'published' ? 'Anbieter veröffentlicht.' : 'Status geändert.');
  }
  res.redirect(back(req, '/admin/anbieter'));
});

router.post('/anbieter/:id/loeschen', (req, res) => {
  providers.remove(req.params.id);
  req.flash('success', 'Anbieter gelöscht.');
  res.redirect('/admin/anbieter');
});

/** Legt (falls nötig) einen Bearbeiter-Zugang an, weist den Anbieter zu und verschickt die Einladung. */
router.post('/anbieter/:id/zugang', wrap(async (req, res, next) => {
  const provider = providers.byId(req.params.id);
  if (!provider) return next();
  const email = str(req.body.email, 150).toLowerCase();
  const name = str(req.body.name, 100) || provider.name;
  if (!isEmail(email)) {
    req.flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
    return res.redirect(`/admin/anbieter/${provider.id}#zugaenge`);
  }
  let user = users.byEmail(email);
  if (!user) user = users.byId(users.create({ email, name, role: 'editor' }));
  users.assign(user.id, provider.id, permsFrom(req.body));

  if (req.body.send_invite && !user.password_hash) {
    inviteFlash(req, await sendInvite(user, res.locals.baseUrl, { providerName: provider.name }), email);
  } else {
    req.flash('success', `Zugriff für ${email} gespeichert.`);
  }
  res.redirect(`/admin/anbieter/${provider.id}#zugaenge`);
}));

/* ---------- Änderungsvorschläge ---------- */

const FIELD_LABELS = {
  master: {
    name: 'Firmenname', legal_form: 'Rechtsform', street: 'Straße', zip: 'PLZ', city: 'Ort', country: 'Land',
    founded_year: 'Gegründet', employees: 'Mitarbeiter', website: 'Website', email: 'E-Mail', phone: 'Telefon',
  },
  description: { short_description: 'Kurzbeschreibung', description: 'Beschreibung' },
};

/** Vergleicht den Live-Stand mit dem Vorschlag – nur die Bereiche, für die der Bearbeiter Rechte hatte. */
function buildDiff(provider, change) {
  const proposed = normalizeInput(change.data);
  const rows = [];
  for (const section of ['master', 'description']) {
    if (!change.sections[section]) continue;
    for (const [key, label] of Object.entries(FIELD_LABELS[section])) {
      const before = provider[key] ?? '';
      const after = proposed[section][key] ?? '';
      rows.push({ label, before: String(before), after: String(after), changed: String(before) !== String(after) });
    }
  }
  let products = null;
  if (change.sections.products) {
    const fmt = (list) => list.map((p) => [p.name, p.description, p.url, p.price_info].filter(Boolean).join(' · '));
    const before = fmt(provider.products);
    const after = fmt(proposed.products);
    products = { before, after, changed: JSON.stringify(before) !== JSON.stringify(after) };
  }
  return { rows, products };
}

router.get('/aenderungen', (req, res) => {
  const status = ['pending', 'approved', 'rejected'].includes(req.query.status) ? req.query.status : 'pending';
  res.render('admin/changes', { title: 'Änderungen', status, changes: changes.list(status) });
});

router.get('/aenderungen/:id', (req, res, next) => {
  const change = changes.byId(req.params.id);
  const provider = change && providers.byId(change.provider_id);
  if (!provider) return next();
  res.render('admin/change', { title: `Änderung: ${provider.name}`, change, provider, diff: buildDiff(provider, change) });
});

router.post('/aenderungen/:id/:action', wrap(async (req, res, next) => {
  const change = changes.byId(req.params.id);
  if (!change || !['freigeben', 'ablehnen'].includes(req.params.action)) return next();
  if (change.status !== 'pending') {
    req.flash('error', 'Diese Änderung wurde bereits bearbeitet.');
    return res.redirect('/admin/aenderungen');
  }
  const approve = req.params.action === 'freigeben';
  const note = str(req.body.note, 1000);
  if (approve) providers.save(change.data, { id: change.provider_id, sections: change.sections });
  changes.decide(change.id, approve ? 'approved' : 'rejected', req.user.id, note);

  if (change.user_email) {
    await sendMail({
      to: change.user_email,
      subject: approve ? `Ihre Änderungen an „${change.provider_name}" sind online` : `Ihre Änderungen an „${change.provider_name}"`,
      text: `Hallo ${change.user_name},\n\n${approve
        ? `Ihre Änderungen am Profil „${change.provider_name}" wurden freigegeben und sind jetzt online.`
        : `Ihre Änderungen am Profil „${change.provider_name}" wurden leider nicht übernommen.`}${note ? `\n\nAnmerkung: ${note}` : ''}\n\n${res.locals.baseUrl}/konto/anbieter/${change.provider_id}\n\nViele Grüße\nIhr Team von Seller's Delight`,
    });
  }
  req.flash('success', approve ? 'Änderung übernommen und online.' : 'Änderung abgelehnt.');
  res.redirect('/admin/aenderungen');
}));

/* ---------- Kategorien ---------- */

router.get('/kategorien', (req, res) => {
  res.render('admin/categories', { title: 'Kategorien', categories: categories.all() });
});

router.post('/kategorien', (req, res) => {
  if (!str(req.body.name)) req.flash('error', 'Bitte einen Namen angeben.');
  else {
    categories.save(req.body);
    req.flash('success', `Kategorie „${str(req.body.name)}" angelegt.`);
  }
  res.redirect('/admin/kategorien');
});

router.post('/kategorien/:id', (req, res) => {
  if (!str(req.body.name)) req.flash('error', 'Bitte einen Namen angeben.');
  else if (categories.byId(req.params.id)) {
    categories.save(req.body, req.params.id);
    req.flash('success', 'Kategorie gespeichert.');
  }
  res.redirect('/admin/kategorien');
});

router.post('/kategorien/:id/loeschen', (req, res) => {
  categories.remove(req.params.id);
  req.flash('success', 'Kategorie gelöscht. Zugeordnete Anbieter bleiben erhalten.');
  res.redirect('/admin/kategorien');
});

/* ---------- Benutzer ---------- */

function permsFrom(body) {
  return { master: !!body.can_edit_master, description: !!body.can_edit_description, products: !!body.can_edit_products };
}

router.get('/benutzer', (req, res) => {
  res.render('admin/users', { title: 'Benutzer & Zugänge', users: users.all() });
});

router.post('/benutzer', wrap(async (req, res) => {
  const email = str(req.body.email, 150).toLowerCase();
  const name = str(req.body.name, 100);
  const role = req.body.role === 'admin' ? 'admin' : 'editor';
  if (!isEmail(email) || !name) {
    req.flash('error', 'Bitte Name und gültige E-Mail-Adresse angeben.');
    return res.redirect('/admin/benutzer');
  }
  if (users.byEmail(email)) {
    req.flash('error', 'Diese E-Mail-Adresse ist bereits registriert.');
    return res.redirect('/admin/benutzer');
  }
  const user = users.byId(users.create({ email, name, role }));
  if (req.body.send_invite) inviteFlash(req, await sendInvite(user, res.locals.baseUrl), email);
  else req.flash('success', 'Benutzer angelegt.');
  res.redirect(`/admin/benutzer/${user.id}`);
}));

router.get('/benutzer/:id', (req, res, next) => {
  const u = users.byId(req.params.id);
  if (!u) return next();
  res.render('admin/user-edit', {
    title: u.name,
    editUser: u,
    assignments: users.assignments(u.id),
    allProviders: providers.list({ status: null, sort: 'name', limit: 5000 }),
  });
});

router.post('/benutzer/:id', (req, res, next) => {
  const u = users.byId(req.params.id);
  if (!u) return next();
  const role = req.body.role === 'admin' ? 'admin' : 'editor';
  const active = !!req.body.active;
  if (u.role === 'admin' && (role !== 'admin' || !active) && users.countAdmins() <= 1) {
    req.flash('error', 'Der letzte aktive Administrator kann nicht herabgestuft oder deaktiviert werden.');
  } else {
    users.update(u.id, { name: str(req.body.name, 100) || u.name, role, active });
    req.flash('success', 'Benutzer gespeichert.');
  }
  res.redirect(`/admin/benutzer/${u.id}`);
});

router.post('/benutzer/:id/einladen', wrap(async (req, res, next) => {
  const u = users.byId(req.params.id);
  if (!u) return next();
  const first = users.assignments(u.id)[0];
  inviteFlash(req, await sendInvite(u, res.locals.baseUrl, { providerName: first?.provider_name }), u.email);
  res.redirect(`/admin/benutzer/${u.id}`);
}));

router.post('/benutzer/:id/zuweisen', (req, res, next) => {
  const u = users.byId(req.params.id);
  const p = providers.byId(req.body.provider_id);
  if (!u) return next();
  if (!p) req.flash('error', 'Bitte einen Anbieter auswählen.');
  else {
    users.assign(u.id, p.id, permsFrom(req.body));
    req.flash('success', `Rechte für „${p.name}" gespeichert.`);
  }
  res.redirect(back(req, `/admin/benutzer/${u.id}`));
});

router.post('/benutzer/:id/zuweisung/:pid/entfernen', (req, res) => {
  users.unassign(req.params.id, req.params.pid);
  req.flash('success', 'Zuweisung entfernt.');
  res.redirect(back(req, `/admin/benutzer/${req.params.id}`));
});

router.post('/benutzer/:id/loeschen', (req, res, next) => {
  const u = users.byId(req.params.id);
  if (!u) return next();
  if (u.id === req.user.id) req.flash('error', 'Sie können sich nicht selbst löschen.');
  else if (u.role === 'admin' && users.countAdmins() <= 1) req.flash('error', 'Der letzte Administrator kann nicht gelöscht werden.');
  else {
    users.remove(u.id);
    req.flash('success', 'Benutzer gelöscht.');
    return res.redirect('/admin/benutzer');
  }
  res.redirect(`/admin/benutzer/${u.id}`);
});

module.exports = router;
