'use strict';

const { db, transaction } = require('./db');
const { uniqueSlug, normalizeUrl, str, toArray } = require('./util');

// Bayes-Gewichtung: Anbieter mit wenigen Bewertungen werden zum Mittelwert hin gezogen,
// damit eine einzelne 5-Sterne-Bewertung nicht automatisch Platz 1 bedeutet.
const PRIOR_WEIGHT = 3;
const PRIOR_MEAN = 3.5;

const STATS_SELECT = `
  p.*,
  COUNT(r.id) AS review_count,
  AVG(r.rating) AS avg_rating,
  (${PRIOR_WEIGHT} * ${PRIOR_MEAN} + COALESCE(SUM(r.rating), 0)) / (${PRIOR_WEIGHT} + COUNT(r.id)) AS score
`;

const SORTS = {
  rating: 'score DESC, review_count DESC, p.name COLLATE NOCASE',
  reviews: 'review_count DESC, score DESC, p.name COLLATE NOCASE',
  name: 'p.name COLLATE NOCASE',
  newest: 'p.created_at DESC',
};

/* ---------- Kategorien ---------- */

const categories = {
  all() {
    return db.prepare(`
      SELECT c.*, (
        SELECT COUNT(*) FROM provider_categories pc
        JOIN providers p ON p.id = pc.provider_id AND p.status = 'published'
        WHERE pc.category_id = c.id
      ) AS provider_count
      FROM categories c ORDER BY c.sort_order, c.name COLLATE NOCASE
    `).all();
  },
  bySlug(slug) {
    return db.prepare('SELECT * FROM categories WHERE slug = ?').get(slug);
  },
  byId(id) {
    return db.prepare('SELECT * FROM categories WHERE id = ?').get(id);
  },
  save(data, id = null) {
    const name = str(data.name, 80);
    const fields = {
      name,
      description: str(data.description, 300),
      icon: str(data.icon, 30) || 'box',
      sort_order: parseInt(data.sort_order, 10) || 0,
    };
    if (id) {
      db.prepare('UPDATE categories SET name=?, description=?, icon=?, sort_order=?, slug=? WHERE id=?')
        .run(fields.name, fields.description, fields.icon, fields.sort_order,
          uniqueSlug(db, 'categories', data.slug || name, Number(id)), id);
      return Number(id);
    }
    const res = db.prepare('INSERT INTO categories (name, slug, description, icon, sort_order) VALUES (?,?,?,?,?)')
      .run(fields.name, uniqueSlug(db, 'categories', data.slug || name), fields.description, fields.icon, fields.sort_order);
    return Number(res.lastInsertRowid);
  },
  remove(id) {
    db.prepare('DELETE FROM categories WHERE id = ?').run(id);
  },
};

/* ---------- Anbieter ---------- */

const providers = {
  list({ categoryId = null, status = 'published', search = '', sort = 'rating', limit = 500 } = {}) {
    const where = [];
    const params = [];
    if (status) { where.push('p.status = ?'); params.push(status); }
    if (categoryId) {
      where.push('p.id IN (SELECT provider_id FROM provider_categories WHERE category_id = ?)');
      params.push(categoryId);
    }
    if (search) {
      where.push('(p.name LIKE ? OR p.short_description LIKE ? OR p.city LIKE ? OR p.description LIKE ?)');
      const like = `%${search}%`;
      params.push(like, like, like, like);
    }
    const rows = db.prepare(`
      SELECT ${STATS_SELECT}
      FROM providers p
      LEFT JOIN reviews r ON r.provider_id = p.id AND r.status = 'approved'
      ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
      GROUP BY p.id
      ORDER BY ${SORTS[sort] || SORTS.rating}
      LIMIT ${Number(limit) || 500}
    `).all(...params);
    return attachCategories(rows);
  },

  topRated(limit = 6) {
    return this.list({ sort: 'rating', limit }).filter((p) => p.review_count > 0);
  },

  bySlug(slug) {
    return this.withStats(db.prepare('SELECT id FROM providers WHERE slug = ?').get(slug)?.id);
  },

  byId(id) {
    return this.withStats(id);
  },

  withStats(id) {
    if (!id) return null;
    const p = db.prepare(`
      SELECT ${STATS_SELECT} FROM providers p
      LEFT JOIN reviews r ON r.provider_id = p.id AND r.status = 'approved'
      WHERE p.id = ? GROUP BY p.id
    `).get(id);
    if (!p) return null;
    attachCategories([p]);
    p.products = db.prepare('SELECT * FROM products WHERE provider_id = ? ORDER BY sort_order, id').all(id);
    return p;
  },

  ratingDistribution(id) {
    const dist = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    for (const row of db.prepare(`
      SELECT rating, COUNT(*) AS n FROM reviews WHERE provider_id = ? AND status = 'approved' GROUP BY rating
    `).all(id)) dist[row.rating] = row.n;
    return dist;
  },

  /**
   * Speichert einen Anbieter. `sections` legt fest, welche Bereiche übernommen werden
   * (Stammdaten, Beschreibung, Produkte, Kategorien, Status) – so nutzen Admin, externe
   * Bearbeiter und das öffentliche Formular dieselbe Logik mit unterschiedlichen Rechten.
   */
  save(data, { id = null, sections = {}, status } = {}) {
    return transaction(() => {
      let providerId = id ? Number(id) : null;

      if (!providerId) {
        const name = str(data.name, 150);
        const res = db.prepare('INSERT INTO providers (name, slug, status, submitted_by_name, submitted_by_email) VALUES (?,?,?,?,?)')
          .run(name, uniqueSlug(db, 'providers', name), status || 'pending',
            str(data.submitted_by_name, 100), str(data.submitted_by_email, 150));
        providerId = Number(res.lastInsertRowid);
      } else if (status) {
        db.prepare('UPDATE providers SET status = ? WHERE id = ?').run(status, providerId);
      }

      if (sections.master) {
        const founded = parseInt(data.founded_year, 10);
        const name = str(data.name, 150);
        db.prepare(`
          UPDATE providers SET name=?, slug=?, legal_form=?, street=?, zip=?, city=?, country=?,
            founded_year=?, employees=?, website=?, email=?, phone=?
          WHERE id=?
        `).run(
          name, uniqueSlug(db, 'providers', data.slug || name, providerId),
          str(data.legal_form, 40), str(data.street, 150), str(data.zip, 15), str(data.city, 100),
          str(data.country, 60) || 'Deutschland',
          founded >= 1800 && founded <= 2100 ? founded : null,
          str(data.employees, 30), normalizeUrl(data.website), str(data.email, 150), str(data.phone, 50),
          providerId,
        );
      }

      if (sections.description) {
        db.prepare('UPDATE providers SET short_description=?, description=? WHERE id=?')
          .run(str(data.short_description, 240), str(data.description, 20000), providerId);
      }

      if (sections.categories) {
        db.prepare('DELETE FROM provider_categories WHERE provider_id = ?').run(providerId);
        const ins = db.prepare('INSERT OR IGNORE INTO provider_categories (provider_id, category_id) VALUES (?, ?)');
        for (const cid of toArray(data.category_ids)) {
          if (categories.byId(Number(cid))) ins.run(providerId, Number(cid));
        }
      }

      if (sections.products) {
        db.prepare('DELETE FROM products WHERE provider_id = ?').run(providerId);
        const names = toArray(data.product_name);
        const descs = toArray(data.product_description);
        const urls = toArray(data.product_url);
        const prices = toArray(data.product_price);
        const ins = db.prepare('INSERT INTO products (provider_id, name, description, url, price_info, sort_order) VALUES (?,?,?,?,?,?)');
        names.slice(0, 30).forEach((n, i) => {
          const name = str(n, 150);
          if (!name) return;
          ins.run(providerId, name, str(descs[i], 1000), normalizeUrl(urls[i]), str(prices[i], 100), i);
        });
      }

      db.prepare("UPDATE providers SET updated_at = datetime('now') WHERE id = ?").run(providerId);
      return providerId;
    });
  },

  setStatus(id, status) {
    db.prepare("UPDATE providers SET status = ?, updated_at = datetime('now') WHERE id = ?").run(status, id);
  },

  remove(id) {
    db.prepare('DELETE FROM providers WHERE id = ?').run(id);
  },

  countByStatus() {
    const out = { pending: 0, published: 0, rejected: 0 };
    for (const r of db.prepare('SELECT status, COUNT(*) n FROM providers GROUP BY status').all()) out[r.status] = r.n;
    return out;
  },
};

function attachCategories(rows) {
  if (!rows.length) return rows;
  const ids = rows.map((r) => r.id);
  const links = db.prepare(`
    SELECT pc.provider_id, c.id, c.name, c.slug FROM provider_categories pc
    JOIN categories c ON c.id = pc.category_id
    WHERE pc.provider_id IN (${ids.map(() => '?').join(',')})
    ORDER BY c.sort_order, c.name
  `).all(...ids);
  for (const r of rows) r.categories = links.filter((l) => l.provider_id === r.id);
  return rows;
}

/* ---------- Bewertungen ---------- */

const reviews = {
  forProvider(providerId) {
    return db.prepare(`
      SELECT * FROM reviews WHERE provider_id = ? AND status = 'approved' ORDER BY created_at DESC
    `).all(providerId);
  },
  list(status = 'pending') {
    return db.prepare(`
      SELECT r.*, p.name AS provider_name, p.slug AS provider_slug, p.status AS provider_status
      FROM reviews r JOIN providers p ON p.id = r.provider_id
      ${status ? 'WHERE r.status = ?' : ''}
      ORDER BY r.created_at ${status === 'pending' ? 'ASC' : 'DESC'}
      LIMIT 500
    `).all(...(status ? [status] : []));
  },
  create({ providerId, rating, title, comment, authorName, authorEmail }) {
    return db.prepare(`
      INSERT INTO reviews (provider_id, rating, title, comment, author_name, author_email) VALUES (?,?,?,?,?,?)
    `).run(providerId, rating, title, comment, authorName, authorEmail);
  },
  existsFor(providerId, email) {
    return !!db.prepare(`
      SELECT 1 FROM reviews WHERE provider_id = ? AND author_email = ? COLLATE NOCASE AND status != 'rejected'
    `).get(providerId, email);
  },
  moderate(id, status, userId) {
    db.prepare("UPDATE reviews SET status = ?, moderated_at = datetime('now'), moderated_by = ? WHERE id = ?")
      .run(status, userId, id);
  },
  remove(id) {
    db.prepare('DELETE FROM reviews WHERE id = ?').run(id);
  },
  countByStatus() {
    const out = { pending: 0, approved: 0, rejected: 0 };
    for (const r of db.prepare('SELECT status, COUNT(*) n FROM reviews GROUP BY status').all()) out[r.status] = r.n;
    return out;
  },
  recentApproved(limit = 4) {
    return db.prepare(`
      SELECT r.*, p.name AS provider_name, p.slug AS provider_slug FROM reviews r
      JOIN providers p ON p.id = r.provider_id AND p.status = 'published'
      WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT ?
    `).all(limit);
  },
};

/* ---------- Benutzer & Bearbeitungsrechte ---------- */

const users = {
  all() {
    return db.prepare(`
      SELECT u.*, (SELECT COUNT(*) FROM provider_editors pe WHERE pe.user_id = u.id) AS provider_count
      FROM users u ORDER BY u.role, u.name COLLATE NOCASE
    `).all();
  },
  byId(id) { return db.prepare('SELECT * FROM users WHERE id = ?').get(id); },
  byEmail(email) { return db.prepare('SELECT * FROM users WHERE email = ?').get(email); },
  byInviteToken(t) {
    return db.prepare('SELECT * FROM users WHERE invite_token = ? AND invite_expires > ?').get(t, Date.now());
  },
  create({ email, name, role }) {
    const res = db.prepare('INSERT INTO users (email, name, role) VALUES (?,?,?)').run(email, name, role);
    return Number(res.lastInsertRowid);
  },
  update(id, { name, role, active }) {
    db.prepare('UPDATE users SET name=?, role=?, active=? WHERE id=?').run(name, role, active ? 1 : 0, id);
  },
  setInvite(id, tokenValue, expires) {
    db.prepare('UPDATE users SET invite_token=?, invite_expires=? WHERE id=?').run(tokenValue, expires, id);
  },
  setPassword(id, hash) {
    db.prepare('UPDATE users SET password_hash=?, invite_token=NULL, invite_expires=NULL WHERE id=?').run(hash, id);
  },
  touchLogin(id) {
    db.prepare("UPDATE users SET last_login_at = datetime('now') WHERE id = ?").run(id);
  },
  remove(id) { db.prepare('DELETE FROM users WHERE id = ?').run(id); },
  countAdmins() { return db.prepare("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1").get().n; },

  assignments(userId) {
    return db.prepare(`
      SELECT pe.*, p.name AS provider_name, p.slug AS provider_slug, p.status AS provider_status
      FROM provider_editors pe JOIN providers p ON p.id = pe.provider_id
      WHERE pe.user_id = ? ORDER BY p.name COLLATE NOCASE
    `).all(userId);
  },
  assignment(userId, providerId) {
    return db.prepare('SELECT * FROM provider_editors WHERE user_id = ? AND provider_id = ?').get(userId, providerId);
  },
  editorsOf(providerId) {
    return db.prepare(`
      SELECT pe.*, u.name, u.email FROM provider_editors pe JOIN users u ON u.id = pe.user_id
      WHERE pe.provider_id = ? ORDER BY u.name
    `).all(providerId);
  },
  assign(userId, providerId, perms) {
    db.prepare(`
      INSERT INTO provider_editors (user_id, provider_id, can_edit_master, can_edit_description, can_edit_products)
      VALUES (?,?,?,?,?)
      ON CONFLICT(user_id, provider_id) DO UPDATE SET
        can_edit_master=excluded.can_edit_master,
        can_edit_description=excluded.can_edit_description,
        can_edit_products=excluded.can_edit_products
    `).run(userId, providerId, perms.master ? 1 : 0, perms.description ? 1 : 0, perms.products ? 1 : 0);
  },
  unassign(userId, providerId) {
    db.prepare('DELETE FROM provider_editors WHERE user_id = ? AND provider_id = ?').run(userId, providerId);
  },
};

module.exports = { categories, providers, reviews, users, SORTS };
