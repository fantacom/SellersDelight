'use strict';

const crypto = require('node:crypto');
const { users } = require('./models');

/** Lädt den angemeldeten Benutzer und stellt CSRF-Token + Flash-Nachrichten bereit. */
function sessionLocals(req, res, next) {
  if (!req.session.csrf) req.session.csrf = crypto.randomBytes(24).toString('base64url');
  res.locals.csrf = req.session.csrf;

  const user = req.session.userId ? users.byId(req.session.userId) : null;
  if (req.session.userId && (!user || !user.active)) delete req.session.userId;
  req.user = user && user.active ? user : null;
  res.locals.user = req.user;

  res.locals.flash = req.session.flash || [];
  delete req.session.flash;
  req.flash = (type, message) => {
    req.session.flash = [...(req.session.flash || []), { type, message }];
  };
  next();
}

function verifyCsrf(req, res, next) {
  if (req.method !== 'POST') return next();
  const sent = String(req.body?._csrf || '');
  const expected = String(req.session.csrf || '');
  if (sent.length && sent.length === expected.length
    && crypto.timingSafeEqual(Buffer.from(sent), Buffer.from(expected))) return next();
  res.status(403).render('public/error', {
    title: 'Sitzung abgelaufen',
    message: 'Das Formular ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
  });
}

function requireLogin(req, res, next) {
  if (req.user) return next();
  req.session.returnTo = req.originalUrl;
  res.redirect('/login');
}

function requireAdmin(req, res, next) {
  if (!req.user) return requireLogin(req, res, next);
  if (req.user.role === 'admin') return next();
  res.status(403).render('public/error', { title: 'Kein Zugriff', message: 'Dieser Bereich ist Administratoren vorbehalten.' });
}

module.exports = { sessionLocals, verifyCsrf, requireLogin, requireAdmin };
