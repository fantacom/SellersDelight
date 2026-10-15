'use strict';

const { users } = require('./models');
const { token } = require('./util');
const { sendMail } = require('./mail');

const INVITE_DAYS = 14;

/**
 * Erzeugt einen Einladungslink (setzt ggf. bestehendes Passwort nicht zurück, erlaubt aber Neuvergabe)
 * und versendet ihn per Mail. Gibt { link, sent } zurück, damit der Link notfalls manuell geteilt werden kann.
 */
async function sendInvite(user, baseUrl, { providerName } = {}) {
  const t = token();
  users.setInvite(user.id, t, Date.now() + INVITE_DAYS * 86400000);
  const link = `${baseUrl}/einladung/${t}`;
  const intro = providerName
    ? `für Ihr Unternehmen „${providerName}" wurde auf Seller's Delight ein Zugang angelegt. Damit können Sie Ihr Profil – z. B. Firmendaten, Beschreibung und Produkte – selbst pflegen.`
    : "für Sie wurde ein Zugang zu Seller's Delight angelegt.";
  const sent = await sendMail({
    to: user.email,
    subject: providerName ? `Ihr Profil „${providerName}" auf Seller's Delight` : "Ihr Zugang zu Seller's Delight",
    text: `Hallo ${user.name},\n\n${intro}\n\nÜber folgenden Link legen Sie Ihr Passwort fest (gültig ${INVITE_DAYS} Tage):\n${link}\n\nViele Grüße\nIhr Team von Seller's Delight`,
  });
  return { link, sent };
}

function inviteFlash(req, { link, sent }, email) {
  if (sent) req.flash('success', `Einladung an ${email} versendet.`);
  else req.flash('info', `E-Mail-Versand nicht konfiguriert. Bitte diesen Einladungslink an ${email} weitergeben: ${link}`);
}

module.exports = { sendInvite, inviteFlash };
