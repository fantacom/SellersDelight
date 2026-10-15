'use strict';

const crypto = require('node:crypto');

function slugify(input) {
  return String(input)
    .toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFKD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80) || 'eintrag';
}

/** Liefert einen in `table` noch freien Slug (optional unter Ausschluss der eigenen ID). */
function uniqueSlug(db, table, base, excludeId = null) {
  const root = slugify(base);
  const stmt = db.prepare(`SELECT id FROM ${table} WHERE slug = ?`);
  for (let i = 1; ; i++) {
    const candidate = i === 1 ? root : `${root}-${i}`;
    const row = stmt.get(candidate);
    if (!row || row.id === excludeId) return candidate;
  }
}

/** Ergänzt fehlendes Protokoll und lässt nur http(s) zu. */
function normalizeUrl(value) {
  const v = String(value || '').trim();
  if (!v) return '';
  const withProto = /^https?:\/\//i.test(v) ? v : `https://${v}`;
  try {
    const u = new URL(withProto);
    return ['http:', 'https:'].includes(u.protocol) ? u.toString() : '';
  } catch {
    return '';
  }
}

function str(value, max = 500) {
  return String(value ?? '').trim().slice(0, max);
}

function isEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || ''));
}

function toArray(value) {
  if (value === undefined || value === null || value === '') return [];
  return Array.isArray(value) ? value : [value];
}

function token(bytes = 32) {
  return crypto.randomBytes(bytes).toString('base64url');
}

/** Einfacher In-Memory-Ratelimiter pro Schlüssel (z. B. IP + Aktion). */
function rateLimiter({ windowMs, max }) {
  const hits = new Map();
  return function hit(key) {
    const now = Date.now();
    const list = (hits.get(key) || []).filter((t) => now - t < windowMs);
    list.push(now);
    hits.set(key, list);
    if (hits.size > 10000) hits.clear();
    return list.length <= max;
  };
}

function formatDate(value) {
  if (!value) return '';
  const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatRating(value) {
  return value ? Number(value).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : '–';
}

/** Gibt Text escaped zurück und wandelt Absätze/Zeilenumbrüche in HTML. */
function paragraphs(text) {
  const esc = String(text || '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  return esc
    .split(/\n{2,}/)
    .map((p) => p.trim())
    .filter(Boolean)
    .map((p) => `<p>${p.replace(/\n/g, '<br>')}</p>`)
    .join('\n');
}

module.exports = {
  slugify, uniqueSlug, normalizeUrl, str, isEmail, toArray, token,
  rateLimiter, formatDate, formatRating, paragraphs,
};
