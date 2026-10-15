'use strict';

const express = require('express');
const bcrypt = require('bcryptjs');
const { users } = require('../models');
const { str, rateLimiter } = require('../util');

const router = express.Router();
const loginLimit = rateLimiter({ windowMs: 15 * 60 * 1000, max: 10 });
const MIN_PASSWORD = 10;

function homeFor(user) {
  return user.role === 'admin' ? '/admin' : '/konto';
}

router.get('/login', (req, res) => {
  if (req.user) return res.redirect(homeFor(req.user));
  res.render('auth/login', { title: 'Anmelden', email: '', error: null });
});

router.post('/login', (req, res, next) => {
  const email = str(req.body.email, 150).toLowerCase();
  const fail = (error) => res.status(401).render('auth/login', { title: 'Anmelden', email, error });

  if (!loginLimit(`login:${req.ip}`)) return fail('Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten.');
  const user = users.byEmail(email);
  if (!user || !user.active || !user.password_hash || !bcrypt.compareSync(String(req.body.password || ''), user.password_hash)) {
    return fail('E-Mail-Adresse oder Passwort ist falsch.');
  }

  const returnTo = req.session.returnTo;
  req.session.regenerate((err) => {
    if (err) return next(err);
    req.session.userId = user.id;
    users.touchLogin(user.id);
    res.redirect(returnTo && returnTo.startsWith('/') && !returnTo.startsWith('//') ? returnTo : homeFor(user));
  });
});

router.post('/logout', (req, res) => {
  req.session.destroy(() => res.redirect('/'));
});

router.get('/einladung/:token', (req, res) => {
  const invited = users.byInviteToken(req.params.token);
  res.render('auth/invite', { title: 'Zugang einrichten', invited, error: null });
});

router.post('/einladung/:token', (req, res, next) => {
  const invited = users.byInviteToken(req.params.token);
  if (!invited) return res.render('auth/invite', { title: 'Zugang einrichten', invited: null, error: null });

  const password = String(req.body.password || '');
  let error = null;
  if (password.length < MIN_PASSWORD) error = `Das Passwort muss mindestens ${MIN_PASSWORD} Zeichen lang sein.`;
  else if (password !== req.body.password_confirm) error = 'Die Passwörter stimmen nicht überein.';
  if (error) return res.status(422).render('auth/invite', { title: 'Zugang einrichten', invited, error });

  users.setPassword(invited.id, bcrypt.hashSync(password, 12));
  req.session.regenerate((err) => {
    if (err) return next(err);
    req.session.userId = invited.id;
    users.touchLogin(invited.id);
    req.flash('success', 'Ihr Zugang ist eingerichtet. Willkommen bei Seller\'s Delight!');
    res.redirect(homeFor(invited));
  });
});

router.MIN_PASSWORD = MIN_PASSWORD;
module.exports = router;
