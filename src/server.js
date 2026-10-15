'use strict';

const path = require('node:path');
const express = require('express');
const session = require('express-session');
const SqliteStore = require('./session-store');
const { bootstrap } = require('./bootstrap');
const { sessionLocals, verifyCsrf } = require('./auth');
const { categories } = require('./models');
const { formatDate, formatRating, paragraphs } = require('./util');

bootstrap();

const app = express();
const PORT = Number(process.env.PORT) || 3000;
const isProd = process.env.NODE_ENV === 'production';

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, '..', 'views'));
app.set('trust proxy', 1);
app.disable('x-powered-by');

app.use((req, res, next) => {
  res.set({
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'SAMEORIGIN',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
    'Content-Security-Policy': "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; form-action 'self'",
  });
  next();
});

app.use(express.static(path.join(__dirname, '..', 'public'), { maxAge: isProd ? '7d' : 0 }));
app.use(express.urlencoded({ extended: false, limit: '200kb' }));

if (isProd && !process.env.SESSION_SECRET) {
  console.error('SESSION_SECRET muss in Produktion gesetzt sein.');
  process.exit(1);
}

app.use(session({
  store: new SqliteStore(),
  secret: process.env.SESSION_SECRET || 'dev-secret-change-me',
  name: 'sd.sid',
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: true, sameSite: 'lax', secure: isProd, maxAge: 7 * 24 * 60 * 60 * 1000 },
}));

app.use(sessionLocals);
app.use(verifyCsrf);

app.locals.siteName = "Seller's Delight";
app.locals.formatDate = formatDate;
app.locals.formatRating = formatRating;
app.locals.paragraphs = paragraphs;
app.locals.contactEmail = process.env.CONTACT_EMAIL || '';

app.use((req, res, next) => {
  res.locals.path = req.path;
  res.locals.navCategories = categories.all();
  res.locals.baseUrl = process.env.BASE_URL || `${req.protocol}://${req.get('host')}`;
  next();
});

app.use('/', require('./routes/auth'));
app.use('/admin', require('./routes/admin'));
app.use('/konto', require('./routes/account'));
app.use('/', require('./routes/public'));

app.use((req, res) => {
  res.status(404).render('public/error', { title: 'Seite nicht gefunden', message: 'Die angeforderte Seite existiert nicht (mehr).' });
});

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).render('public/error', { title: 'Fehler', message: 'Es ist ein unerwarteter Fehler aufgetreten.' });
});

app.listen(PORT, () => console.log(`Seller's Delight läuft auf http://localhost:${PORT}`));
