# Profi-Tipp für Laravel-Entwickler: "Laravel Boost MCP"

Um Claude Code noch mächtiger im Umgang mit Ihrer Datenbank zu machen, empfiehlt sich die Installation des Open-Source-Pakets `laravel/boost` via Composer im Projekt:

```bash
composer require laravel/boost --dev
```

## Dieses Paket registriert sich als sogenannter Model Context Protocol (MCP) Server. Dadurch kann Claude Code direkt mit den Internals Ihrer Laravel-App interagieren. Claude kann dann

- Live Ihre echte lokale Datenbank-Struktur abfragen (ohne die Dateien lesen zu müssen).
- Artisan Tinker-Befehle im Hintergrund ausführen, um Daten direkt zu prüfen.

## Server start and stop script

- Mach im Projektordner eine start.bat, um den Server zu starten
- Mach im Projektordner eine stop.bat, um den Server zu soppen

## Beachte - könnte im Projektkonzept falsch stehen

- Patienten mit hohem Blutdruck benutzen diese App von zu Hause

---

# CardioPulse – Entwicklung

[![CI](https://github.com/CarmenVavra/cardio-pulse-app/actions/workflows/ci.yml/badge.svg)](https://github.com/CarmenVavra/cardio-pulse-app/actions/workflows/ci.yml)

Telemedizin-Plattform für Hypertonie-Patienten zu Hause: Patienten-App (Smartphone) + 24/7-Überwachungsscreen im Krankenhaus.
Umsetzung nach `CardioPulse_Projektkonzept.pdf` und den Mockups in `UI_MOCKUPS/` (Screens D1–D6, M1–M7).

**Stack:** Laravel 12 · Blade · Eloquent · SQLite · Vite (Vanilla JS/CSS, Modernist-Design) · PHPUnit · Pint · Larastan

## Starten / Stoppen

- `start.bat` – gleicht PHP- und npm-Pakete ab, legt die DB mit Demo-Daten an bzw. führt neue Migrationen aus, baut die Assets und startet den Server auf **http://127.0.0.1:8700**; bricht bei Fehlern mit Meldung ab
- `stop.bat` – beendet den Server auf Port 8700
- `upload-assets.bat` – baut CSS/JS und lädt sie auf den Server (Produktivbetrieb ohne Node.js, siehe unten)

Nach einem `git pull` genügt `start.bat` – neue Pakete, Migrationen und geänderte Assets werden dabei automatisch übernommen.
Ohne `start.bat`: `composer install`, `npm install`, `php artisan migrate`, `npm run build`.

## Adressen

| Bereich | URL |
|---|---|
| Krankenhaus (D1 Login → D2 Überwachung) | http://127.0.0.1:8700/login |
| Patienten-App (M1–M7) | http://127.0.0.1:8700/app/login |

## Testzugänge (nur lokal, aus `database/seeders/DatabaseSeeder.php`)

- Krankenhaus: Benutzerkennung `m.weber`, Passwort `cardiopulse`, Privacy-Lock-PIN `123456`
- Patienten-App: `josef.brandner@cardiopulse.test` (bzw. `vorname.nachname@cardiopulse.test`), Passwort `cardiopulse`

Demo-Daten neu erzeugen: `php artisan migrate:fresh --seed`

## Funktionen

**Ampel** (identisch in App und Krankenhaus, `app/Enums/BloodPressureStatus.php`): Rot ≥ 180 / ≥ 120 · Blau (zu niedrig) < 90 / < 60 · Gelb-Orange ≥ 130 / ≥ 85 · Grün sonst (mmHg, systolisch / diastolisch).

### Krankenhaus

| Bereich | Funktion |
|---|---|
| **Überwachung** (D2) | Live-Board mit Triage-Sortierung Rot → Gelb-Orange → Blau (zu niedrig) → Grün, Aktualisierung alle 5 s, Hervorhebung neuer Uploads, Suche, 7-Tage-Sparkline, Hinweis auf neue Patienten-Nachrichten |
| **Alarm** (D3) | Rote Werte lösen Alarm aus – blinkendes Banner/Rahmen, Signalton (Web Audio, 960 Hz) bis zur Pflicht-Quittierung mit Maßnahme; Audit-Log |
| **Privacy-Lock** (D6) | Nach 3 Min Inaktivität; Namen werden serverseitig ausgeblendet, Entsperren per PIN |
| **Patienten** (D4) | 30-Tage-Verlauf, Kennzahlen, Medikation, Chat mit dem Patienten, PDF-Druckansicht, **KIS-Export als HL7 FHIR R4 Bundle** (LOINC 85354-9, 8480-6, 8462-4, 8867-4, Interpretation HH/H/N/L) |
| **Patienten verwalten** | Anlegen (inkl. App-Zugang), Bearbeiten, Löschen mit Bestätigung |
| **Medikation** | Erfassen, Bearbeiten, Löschen; Schema morgens – mittags – abends; Änderungen durch den Patienten werden markiert |
| **Monatsberichte** | Eingegangene Berichte je Monat mit Verlauf des Berichtsmonats |
| **Anrufe** (D5) | Arzt ↔ Patient in beide Richtungen inkl. Klingeln, Annehmen/Ablehnen, Gesprächsdauer, Gesprächsnotiz |
| **Ärzte** | Anlegen, Bearbeiten (Profil, Benutzerkennung, Passwort, PIN), Löschen mit Übergabe der Patienten an einen anderen Arzt; eigenes Konto und letzter Arzt sind geschützt |
| **Mein Konto** (Klick auf den eigenen Namen oben rechts) | Eigenes Passwort und Privacy-Lock-PIN ändern |

### Patienten-App

| Screen | Funktion |
|---|---|
| **Start** (M1) | Letzte Messung, Messungen von heute, Fortschritt Monatsbericht, Medikation, ungelesene Nachrichten |
| **Messen** (M2–M4) | Erfassung per Numpad mit Kontext (Ruhe, Medikation, Symptome), Ergebnis in Ampelfarbe, Notfall-Interruption mit Notruf 112 |
| **Monat** (M5) | Monatskalender, Versand an das Krankenhaus (erneut senden möglich), PDF für den Hausarzt |
| **Arzt** (M6/M7) | Anruf an Arzt oder Zentrale, eingehende Anrufe, **Chat mit der Klinik** (Nachrichten lesen und beantworten, Lesebestätigung) |
| **Medikation** | Eigene Medikation erfassen, bearbeiten, löschen |
| **Mein Konto** (Link auf der Startseite) | Passwort ändern, Abmelden |

### Löschen und Nachverfolgbarkeit

Patienten und Ärzte werden **nicht endgültig gelöscht** (Soft Delete): Die Anmeldung wird sofort gesperrt, offene Sitzungen und Anrufe werden beendet,
aber Messwerte, Alarm-Quittierungen, Anrufe und Audit-Log bleiben erhalten (Aufbewahrungspflicht § 630f BGB, Nachverfolgbarkeit nach MDR / IEC 62304).
Benutzerkennungen und E-Mail-Adressen gelöschter Konten bleiben deshalb reserviert.

Alle sicherheitsrelevanten Aktionen (Alarm quittieren, Export, Anlegen/Ändern/Löschen, Nachrichten) werden im Audit-Log (`audit_logs`) protokolliert.

### Sicherheit

| Maßnahme | Umsetzung |
|---|---|
| Passwort-Raten | Nach 5 Fehlversuchen ist die Anmeldung für dieses Konto (je IP-Adresse) 5 Minuten gesperrt; zusätzlich max. 10 Login-Anfragen pro Minute je IP |
| Privacy-Lock-PIN | Nach 5 falschen PINs wird die Sitzung beendet; die Meldung zeigt die verbleibenden Versuche |
| Passwörter | Einheitlich mind. 8 Zeichen mit Buchstaben und Ziffern (Anlegen, Bearbeiten, eigenes Konto); beim Ändern ist das aktuelle Passwort nötig und alle anderen Sitzungen werden abgemeldet |
| PIN ändern | Passwort nötig; triviale PINs (z. B. 123456, 111111) werden abgelehnt |
| Sitzungen | Datenbank-Sessions, verschlüsselt (`SESSION_ENCRYPT=true`), Abmeldung nach 120 Min ohne Anfrage |
| HTTP-Header | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`; HSTS bei HTTPS |
| HTTPS | Im Produktivbetrieb (`APP_ENV=production`) werden alle Links auf HTTPS erzeugt |
| Audit-Log | Anmeldungen, Fehlversuche, Sperren, Passwort- und PIN-Änderungen werden protokolliert |

Validierungsmeldungen sind deutsch (`lang/de/validation.php`).

### Demo-Modus

„Demo: Upload simulieren" auf dem Board (`CARDIOPULSE_DEMO=false` zum Abschalten).

## Bewusste Vereinfachungen / offene Punkte

- Echtzeit per **Polling** (5 s) statt WebSockets – Austausch gegen Laravel Reverb vorgesehen.
- Anrufe: Signalisierung und Status sind umgesetzt, der **Sprachkanal (WebRTC/VoIP)** ist nicht angebunden.
- **Video-Sprechstunden** mit Terminvergabe (FHIR `Appointment`) und FHIR `Encounter` fehlen noch; der FHIR-Export ist ein Download, keine REST-API mit SMART on FHIR / OAuth 2.0.
- Keine eigene **Admin-Rolle**: Jeder angemeldete Arzt darf Ärzte verwalten.
- Kein **Passwort vergessen** (Zurücksetzen per E-Mail) – Ärzte können Passwörter anderer Ärzte unter „Ärzte“ neu setzen, Patienten-Passwörter unter „Patienten bearbeiten“.
- Der Chat ist **kein Notfallkanal** – die App weist auf 112 hin.
- Bluetooth-Import und Foto-Scan (OCR) sind nur in nativen Apps sinnvoll – in der Web-App erscheint ein Hinweis.
- Hypotonie wird **blau** dargestellt („Blue Ice“ laut Konzept, für WCAG-AA-Kontrast mit weißer Schrift leicht abgedunkelt: `#0277BD` statt `#0288D1`). Das Mockup sah weiß vor – das war zu unauffällig.
- Bereich 130–139 / 85–89 ist im Konzept undefiniert und wird als Gelb-Orange gewertet – **mit medizinischer Leitung abstimmen**.
- Mikro-Labels sind 12 px statt 11 px (Mindestschriftgröße laut CLAUDE.md).

## Produktivbetrieb (netcup-Webhosting)

Die App liegt **außerhalb** des öffentlichen Bereichs; aus dem Internet erreichbar ist nur `public/`.

Die SSH-Shell des netcup-Webhostings hat **kein Node.js** – CSS/JS baut deshalb der eigene PC mit `upload-assets.bat` und lädt sie per SSH hoch (Windows-Bordmittel `tar` und `ssh`; der SSH-Zugang wird in `.deploy-target` gemerkt, nicht in Git). Gibt es auf dem Server Node.js, baut `deploy.sh` die Assets selbst.

### Einmalig im netcup-Panel

1. Im CCP den **Vertrag zur Auftragsverarbeitung (AVV)** abschließen – CardioPulse speichert Gesundheitsdaten.
2. (Sub-)Domain anlegen, **Let's-Encrypt-Zertifikat** ausstellen und HTTP dauerhaft auf HTTPS umleiten.
3. **PHP 8.3** für die Domain einstellen und dieselbe Version als Shell-Standard in `/conf/phpversion` eintragen (Anleitung: `/conf-options/phpversion.readme`). Benötigte Erweiterungen: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`.
4. Unter „Webhosting-Zugang“ ein SSH-Passwort setzen.

### Erstinstallation per SSH

```bash
git clone https://github.com/CarmenVavra/cardio-pulse-app.git cardio-pulse
cd cardio-pulse
cp .env.example .env
nano .env
```

In der `.env` anpassen:

| Variable | Wert |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://cardiopulse.caryssa.at` |
| `LOG_LEVEL` | `warning` |
| `SESSION_ENCRYPT` / `SESSION_SECURE_COOKIE` | `true` / `true` |
| `CARDIOPULSE_DEMO` | `false` (kein Demo-Upload auf dem Board) |
| `DB_CONNECTION` | `sqlite` (Datei `database/database.sqlite` wird angelegt) – alternativ MariaDB mit `mysql` und `DB_*` |
| `TRUSTED_PROXIES` | leer lassen (nur hinter einem eigenen Reverse-Proxy setzen) |

Gibt es eine Zeile doppelt, gilt die untere – geänderte Werte daher direkt in der vorhandenen Zeile eintragen.

Auf dem eigenen PC `upload-assets.bat` ausführen, dann auf dem Server installieren und den ersten Arzt-Zugang anlegen (**keine Demo-Daten** – dieser Arzt legt alle weiteren Ärzte und Patienten in der App an):

```bash
./deploy.sh
php artisan cardiopulse:create-doctor
```

Zum Schluss im Panel den **Document Root** der Domain auf `/cardio-pulse/public` setzen.

### Updates

1. Änderungen auf GitHub pushen, dann auf dem eigenen PC `upload-assets.bat` ausführen (nur nötig, wenn sich CSS/JS geändert hat; warnt, wenn der lokale Stand von GitHub abweicht).
2. Auf dem Server:

   ```bash
   cd cardio-pulse && ./deploy.sh
   ```

`deploy.sh` schaltet die App in den Wartungsmodus, holt den neuesten Stand von GitHub, gleicht die PHP-Pakete ab (lädt Composer als `composer.phar`, falls der Befehl fehlt), baut die Assets (nur mit Node.js 20.19+ / 22.12+), führt Migrationen aus, leert die Laravel-Caches und bricht beim ersten Fehler ab. Eine andere PHP-Version als den Shell-Standard nutzt `PHP=/usr/local/php83/bin/php ./deploy.sh`. Konfiguration, Routen und Views werden bewusst nicht gecacht: Die netcup-SSH-Shell sieht das Projekt unter `/cardio-pulse`, der Webserver unter `/var/www/vhosts/…/cardio-pulse` – ein Cache aus der Shell enthielte falsche Pfade.

**Sicherung:** `database/database.sqlite` und `.env` (enthält den Schlüssel `APP_KEY`).

## Qualitätssicherung

Lokal vor jedem Commit:

```bash
php artisan test
./vendor/bin/pint
./vendor/bin/phpstan analyse
npm run build
```

### GitHub Actions (`.github/workflows/ci.yml`)

Bei jedem Push auf `main` und bei jedem Pull Request:

| Job | Inhalt |
|---|---|
| **Tests, Code-Style, statische Analyse, Build** | `npm run build`, `pint --test`, Larastan (Level 6), `php artisan test` |
| **Sicherheitsprüfung & SBOM** | `composer audit`, `npm audit --audit-level=high`, Software Bill of Materials im CycloneDX-Format (Download als Artefakt `sbom-cyclonedx` im Workflow-Lauf) |

**Dependabot** (`.github/dependabot.yml`) prüft wöchentlich Composer- und npm-Pakete sowie monatlich die GitHub Actions und erstellt bei Updates automatisch Pull Requests.

Laravel Boost ist installiert; der MCP-Server ist in `.mcp.json` registriert (`php artisan boost:mcp`).
