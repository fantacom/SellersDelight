'use strict';

const express = require('express');
const { categories, providers, reviews, SORTS } = require('../models');
const { str, isEmail, rateLimiter, toArray } = require('../util');
const { notifyAdmin } = require('../mail');

const router = express.Router();
const reviewLimit = rateLimiter({ windowMs: 60 * 60 * 1000, max: 10 });
const submitLimit = rateLimiter({ windowMs: 60 * 60 * 1000, max: 5 });

const SORT_LABELS = { rating: 'Beste Bewertung', reviews: 'Meiste Bewertungen', name: 'Name (A–Z)', newest: 'Neueste' };

router.get('/', (req, res) => {
  res.render('public/home', {
    title: null,
    categories: res.locals.navCategories,
    topRated: providers.topRated(6),
    recentReviews: reviews.recentApproved(3),
  });
});

router.get('/kategorie/:slug', (req, res, next) => {
  const category = categories.bySlug(req.params.slug);
  if (!category) return next();
  const sort = SORTS[req.query.sort] ? req.query.sort : 'rating';
  res.render('public/category', {
    title: category.name,
    category,
    sort,
    sortLabels: SORT_LABELS,
    providers: providers.list({ categoryId: category.id, sort }),
  });
});

router.get('/suche', (req, res) => {
  const q = str(req.query.q, 100);
  res.render('public/search', {
    title: q ? `Suche: ${q}` : 'Suche',
    q,
    providers: q ? providers.list({ search: q, sort: 'rating', limit: 100 }) : [],
  });
});

function renderProvider(req, res, next, form = {}, errors = []) {
  const provider = providers.bySlug(req.params.slug);
  if (!provider) return next();
  const isStaff = req.user?.role === 'admin';
  if (provider.status !== 'published' && !isStaff) return next();
  res.status(errors.length ? 422 : 200).render('public/provider', {
    title: provider.name,
    provider,
    reviews: reviews.forProvider(provider.id),
    distribution: providers.ratingDistribution(provider.id),
    form,
    errors,
  });
}

router.get('/anbieter/:slug', (req, res, next) => renderProvider(req, res, next));

router.post('/anbieter/:slug/bewerten', (req, res, next) => {
  const provider = providers.bySlug(req.params.slug);
  if (!provider || provider.status !== 'published') return next();

  // Honeypot: von Menschen unsichtbar, von Bots oft ausgefüllt.
  if (req.body.homepage) {
    req.flash('success', 'Vielen Dank! Ihre Bewertung wird geprüft und anschließend veröffentlicht.');
    return res.redirect(`/anbieter/${provider.slug}`);
  }

  const form = {
    rating: parseInt(req.body.rating, 10),
    title: str(req.body.title, 120),
    comment: str(req.body.comment, 4000),
    author_name: str(req.body.author_name, 80),
    author_email: str(req.body.author_email, 150).toLowerCase(),
    consent: !!req.body.consent,
  };
  const errors = [];
  if (!(form.rating >= 1 && form.rating <= 5)) errors.push('Bitte vergeben Sie 1 bis 5 Sterne.');
  if (form.comment.length < 20) errors.push('Bitte beschreiben Sie Ihre Erfahrung in mindestens 20 Zeichen.');
  if (!form.author_name) errors.push('Bitte geben Sie einen Namen an (wird öffentlich angezeigt).');
  if (!isEmail(form.author_email)) errors.push('Bitte geben Sie eine gültige E-Mail-Adresse an (wird nicht veröffentlicht).');
  if (!form.consent) errors.push('Bitte bestätigen Sie die Datenschutzhinweise.');
  if (!errors.length && reviews.existsFor(provider.id, form.author_email)) {
    errors.push('Mit dieser E-Mail-Adresse wurde dieser Anbieter bereits bewertet.');
  }
  if (!errors.length && !reviewLimit(`review:${req.ip}`)) errors.push('Zu viele Bewertungen in kurzer Zeit. Bitte später erneut versuchen.');
  if (errors.length) return renderProvider(req, res, next, form, errors);

  reviews.create({
    providerId: provider.id, rating: form.rating, title: form.title, comment: form.comment,
    authorName: form.author_name, authorEmail: form.author_email,
  });
  notifyAdmin(`Neue Bewertung für ${provider.name}`, `${form.rating}/5 Sterne von ${form.author_name}\n\n${form.comment}\n\nFreigabe: ${res.locals.baseUrl}/admin/bewertungen`);
  req.flash('success', 'Vielen Dank! Ihre Bewertung wird geprüft und anschließend veröffentlicht.');
  res.redirect(`/anbieter/${provider.slug}#bewertungen`);
});

router.get('/anbieter-eintragen', (req, res) => {
  const preset = categories.bySlug(String(req.query.kategorie || ''));
  res.render('public/submit', {
    title: 'Anbieter eintragen',
    provider: { category_ids: preset ? [preset.id] : [], products: [] },
    errors: [],
  });
});

router.post('/anbieter-eintragen', (req, res) => {
  if (req.body.homepage) {
    req.flash('success', 'Vielen Dank! Wir prüfen den Eintrag und schalten ihn anschließend frei.');
    return res.redirect('/');
  }
  const errors = [];
  if (!str(req.body.name)) errors.push('Bitte geben Sie den Firmennamen an.');
  if (!toArray(req.body.category_ids).length) errors.push('Bitte wählen Sie mindestens eine Kategorie.');
  if (!str(req.body.short_description)) errors.push('Bitte geben Sie eine Kurzbeschreibung an.');
  if (!str(req.body.submitted_by_name)) errors.push('Bitte geben Sie Ihren Namen an.');
  if (!isEmail(req.body.submitted_by_email)) errors.push('Bitte geben Sie eine gültige E-Mail-Adresse an.');
  if (!req.body.consent) errors.push('Bitte bestätigen Sie die Datenschutzhinweise.');
  if (!errors.length && !submitLimit(`submit:${req.ip}`)) errors.push('Zu viele Einreichungen in kurzer Zeit. Bitte später erneut versuchen.');

  if (errors.length) {
    return res.status(422).render('public/submit', {
      title: 'Anbieter eintragen',
      provider: formToProvider(req.body),
      errors,
    });
  }

  providers.save(req.body, {
    sections: { master: true, description: true, categories: true, products: true },
    status: 'pending',
  });
  notifyAdmin(`Neuer Anbieter eingereicht: ${str(req.body.name)}`, `Eingereicht von ${str(req.body.submitted_by_name)} <${str(req.body.submitted_by_email)}>\n\nPrüfen: ${res.locals.baseUrl}/admin/anbieter?status=pending`);
  req.flash('success', 'Vielen Dank! Wir prüfen den Eintrag und schalten ihn anschließend frei.');
  res.redirect('/');
});

router.get('/impressum', (req, res) => res.render('public/legal', { title: 'Impressum', page: 'impressum' }));
router.get('/datenschutz', (req, res) => res.render('public/legal', { title: 'Datenschutz', page: 'datenschutz' }));

/** Baut aus abgeschickten Formulardaten ein Objekt zum erneuten Befüllen des Formulars. */
function formToProvider(body) {
  const names = toArray(body.product_name);
  return {
    ...body,
    category_ids: toArray(body.category_ids).map(Number),
    products: names.map((name, i) => ({
      name,
      description: toArray(body.product_description)[i] || '',
      url: toArray(body.product_url)[i] || '',
      price_info: toArray(body.product_price)[i] || '',
    })),
  };
}

router.formToProvider = formToProvider;
module.exports = router;
