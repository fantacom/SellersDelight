# Seller's Delight

Bewertungsplattform für Dienstleister von E-Commerce-Sellern (Fotostudios, Agenturen, Steuerberater, 3PL …).
Läuft auf normalem PHP-Webhosting (z. B. IONOS Webhosting) mit MySQL/MariaDB – ohne zusätzliche Software.

## Funktionen

**Öffentlich**
- Kategorien-Übersicht, Ranking je Kategorie (Bayes-gewichteter Durchschnitt, damit eine einzelne 5-Sterne-Bewertung nicht automatisch Platz 1 ist), Sortierung nach Bewertung / Anzahl / Name / neu
- Anbieter-Profil: Firmendaten, ausführliche Beschreibung, Produkte mit Links und Preis-Info, Bewertungen inkl. Verteilung
- Bewertung abgeben und neuen Anbieter eintragen – beides erst nach Freigabe sichtbar
- Suche

**Backend (`/admin`, Administratoren)**
- Bewertungen, neue Anbieter und Profil-Änderungen freigeben / ablehnen
- Anbieter, Kategorien und Benutzer verwalten
- Dritten (z. B. dem Dienstleister selbst) Zugriff auf einzelne Anbieter geben – je Bereich: Firmendaten, Beschreibung, Produkte. Einladung per E-Mail.

**Bearbeiter (`/konto`)**
- Sieht nur zugewiesene Anbieter, bearbeitet nur freigegebene Bereiche. Änderungen werden erst nach Freigabe im Backend übernommen (mit Vorher/Nachher-Vergleich).

## Installation bei IONOS

1. **SSL aktivieren:** IONOS → Domains & SSL → sellersdelight.de → SSL-Zertifikat „Aktivieren“. (Die Seite leitet automatisch auf `https://www.` um – ohne Zertifikat ist sie nicht erreichbar.)
2. **PHP-Version prüfen:** Hosting → PHP-Einstellungen → mindestens PHP 8.1 (empfohlen 8.3).
3. **Datenbank anlegen:** Hosting → Datenbanken → „Datenbank anlegen“ (MySQL/MariaDB). Hostname, Datenbankname, Benutzername und Passwort notieren.
4. **Dateien hochladen:** Inhalt des Projekts (bzw. der ZIP-Datei) in ein Verzeichnis `sellersdelight` im Webspace hochladen – per Webspace-Explorer im IONOS-Kundenbereich oder per SFTP.
5. **Domain verbinden:** Domains & SSL → sellersdelight.de → Verwendungsart/Ziel → Webspace → Verzeichnis `/sellersdelight/public` wählen. (Zeigt die Domain auf `/sellersdelight`, funktioniert es auch – App-Dateien sind per `.htaccess` gesperrt.)
6. **Installation im Browser:** `https://www.sellersdelight.de` öffnen → Datenbankdaten eintragen → anschließend den eigenen Administrator-Zugang anlegen.
   Bitte direkt nach dem Hochladen erledigen: Bis dahin ist der Assistent für jeden erreichbar.
7. **Impressum und Datenschutz** in `templates/public/legal.php` ausfüllen.

E-Mails (Einladungen, Benachrichtigungen) werden über die PHP-Mailfunktion von IONOS versendet. Kommt eine Einladung nicht an, zeigt das Backend den Link zum manuellen Weitergeben an.

## Struktur

```
public/      Webroot: index.php, CSS, JS, Logos
app/         Logik (Datenbank, Modelle, Routen, Mail, Installer)
templates/   HTML-Vorlagen
tools/       seed-demo.php: fiktive Beispieldaten (Kommandozeile)
config.php   wird vom Installationsassistenten erzeugt (nicht im Git)
```

## Lokal entwickeln

```bash
cp config.sample.php config.php            # lokale MySQL-Daten eintragen, 'debug' => true
php -S localhost:8000 -t public public/index.php
php tools/seed-demo.php                    # optional: Beispieldaten
```

## Logo

`public/img/logo.svg` (Wort-Bild-Marke), `logo-negativ.svg` (dunkle Hintergründe), `logo-mark.svg` (Bildmarke), `favicon.svg`.
