'use strict';

const session = require('express-session');
const { db } = require('./db');

/** Persistenter Session-Store auf Basis der SQLite-Datenbank. */
class SqliteStore extends session.Store {
  constructor() {
    super();
    this.getStmt = db.prepare('SELECT data FROM sessions WHERE sid = ? AND expires > ?');
    this.setStmt = db.prepare(`INSERT INTO sessions (sid, data, expires) VALUES (?,?,?)
      ON CONFLICT(sid) DO UPDATE SET data = excluded.data, expires = excluded.expires`);
    this.delStmt = db.prepare('DELETE FROM sessions WHERE sid = ?');
    this.touchStmt = db.prepare('UPDATE sessions SET expires = ? WHERE sid = ?');
    setInterval(() => db.prepare('DELETE FROM sessions WHERE expires <= ?').run(Date.now()), 60 * 60 * 1000).unref();
  }

  expiry(sess) {
    return sess?.cookie?.expires ? new Date(sess.cookie.expires).getTime() : Date.now() + 86400000;
  }

  get(sid, cb) {
    try {
      const row = this.getStmt.get(sid, Date.now());
      cb(null, row ? JSON.parse(row.data) : null);
    } catch (err) { cb(err); }
  }

  set(sid, sess, cb) {
    try { this.setStmt.run(sid, JSON.stringify(sess), this.expiry(sess)); cb?.(null); } catch (err) { cb?.(err); }
  }

  destroy(sid, cb) {
    try { this.delStmt.run(sid); cb?.(null); } catch (err) { cb?.(err); }
  }

  touch(sid, sess, cb) {
    try { this.touchStmt.run(this.expiry(sess), sid); cb?.(null); } catch (err) { cb?.(err); }
  }
}

module.exports = SqliteStore;
