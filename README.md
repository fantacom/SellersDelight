# Seller's Delight

Bewertungsplattform für Dienstleister von E-Commerce-Sellern (Fotostudios, Agenturen, Steuerberater, 3PL …).

## Funktionen

**Öffentlich**
- Kategorien-Übersicht, Ranking je Kategorie (Bayes-gewichteter Durchschnitt, damit eine einzelne 5-Sterne-Bewertung nicht automatisch Platz 1 ist), Sortierung nach Bewertung / Anzahl / Name / neu
- Anbieter-Profil: Firmendaten (Adresse, Gründungsjahr, Mitarbeiter, Kontakt), ausführliche Beschreibung, Produkte mit Links und Preis-Info, Bewertungen inkl. Verteilung
- Bewertung abgeben (1–5 Sterne + Text) – erscheint erst nach Freigabe
- Neuen Anbieter eintragen – erscheint erst nach Freigabe
- Volltextsuche

**Backend (`/admin`, Rolle Administrator)**
- Bewertungen freigeben / ablehnen / löschen
- Eingereichte Anbieter freigeben / ablehnen, alle Anbieter anlegen und bearbeiten
- Kategorien anlegen, umbenennen, sortieren, löschen
- Benutzer anlegen (Administrator oder Bearbeiter), Einladungslink per E-Mail
- Dritten (z. B. dem Dienstleister selbst) Zugriff auf einzelne Anbieter geben – je Bereich steuerbar: Firmendaten, Beschreibung, Produkte. Direkt aus dem Anbieter-Profil: „Zugang anlegen" verschickt „Für Sie wurde ein Zugang angelegt …"

**Bearbeiter (`/konto`)**
- Sieht nur zugewiesene Anbieter, bearbeitet nur freigegebene Bereiche.
- Änderungen gehen als Vorschlag ins Backend („Profil-Änderungen") und werden erst nach Freigabe übernommen – mit Vorher/Nachher-Vergleich und optionaler Anmerkung per E-Mail an den Bearbeiter.

## Technik

Node.js ≥ 22.13, Express, EJS, SQLite (eingebaut in Node, keine externe Datenbank). Keine externen Schriften/Tracker. CSRF-Schutz, bcrypt-Passwörter, Login-Ratelimit, Honeypot gegen Spam.

## Start

```bash
npm install
cp .env.example .env      # Werte eintragen
npm start                 # http://localhost:3000
npm run seed:demo         # optional: fiktive Beispieldaten
```

Beim ersten Start werden Standard-Kategorien und ein Administrator angelegt (`ADMIN_EMAIL`/`ADMIN_PASSWORD`; ohne Passwort wird eines generiert und im Log ausgegeben).

Ohne SMTP-Konfiguration zeigt das Backend Einladungslinks direkt an, damit sie manuell weitergegeben werden können.

### Docker

```bash
docker build -t sellersdelight .
docker run -p 3000:3000 -v sd-data:/data --env-file .env sellersdelight
```

In Produktion hinter einem HTTPS-Reverse-Proxy betreiben (`NODE_ENV=production` setzt sichere Cookies).

## Logo

`public/img/logo.svg` (Wort-Bild-Marke), `logo-negativ.svg` (für dunkle Hintergründe), `logo-mark.svg` (Bildmarke/App-Icon), `favicon.svg`.

## Vor dem Livegang

- Impressum und Datenschutzerklärung in `views/public/legal.ejs` ausfüllen
- `SESSION_SECRET` setzen, Datenbankverzeichnis sichern (Backup)
