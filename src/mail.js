'use strict';

const nodemailer = require('nodemailer');

const transport = process.env.SMTP_HOST
  ? nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: Number(process.env.SMTP_PORT) || 587,
    secure: process.env.SMTP_SECURE === 'true',
    auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS } : undefined,
  })
  : null;

const FROM = process.env.MAIL_FROM || "Seller's Delight <no-reply@sellersdelight.de>";

const mailEnabled = () => !!transport;

/** Versendet eine Mail. Ohne SMTP-Konfiguration wird nur geloggt; Rückgabe: true bei Versand. */
async function sendMail({ to, subject, text }) {
  if (!transport) {
    console.log(`[mail] SMTP nicht konfiguriert – nicht versendet an ${to}: ${subject}`);
    return false;
  }
  try {
    await transport.sendMail({ from: FROM, to, subject, text });
    return true;
  } catch (err) {
    console.error('[mail] Versand fehlgeschlagen:', err.message);
    return false;
  }
}

/** Benachrichtigt den Betreiber über neue Einreichungen (optional, via ADMIN_NOTIFY_EMAIL). */
function notifyAdmin(subject, text) {
  if (process.env.ADMIN_NOTIFY_EMAIL) sendMail({ to: process.env.ADMIN_NOTIFY_EMAIL, subject, text });
}

module.exports = { sendMail, notifyAdmin, mailEnabled };
