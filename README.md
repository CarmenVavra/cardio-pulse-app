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
- `backup.bat` – holt eine Sicherung von Datenbank und `.env` vom Server nach `backups\` (siehe unten)

Nach einem `git pull` genügt `start.bat` – neue Pakete, Migrationen und geänderte Assets werden dabei automatisch übernommen.
Ohne `start.bat`: `composer install`, `npm install`, `php artisan migrate`, `npm run build`.

## Adressen

| Bereich | URL |
|---|---|
| Krankenhaus (D1 Login → D2 Überwachung) | http://127.0.0.1:8700/login |
| Patienten-App (M1–M7) | http://127.0.0.1:8700/app/login |

## Testzugänge (nur lokal, aus `database/seeders/DatabaseSeeder.php`)

- Krankenhaus: Benutzerkennung `m.weber` (Admin), Passwort `cardiopulse`, Privacy-Lock-PIN `123456` – `t.krause` ist Arzt ohne Admin-Rechte
- Patienten-App: `josef.brandner@cardiopulse.test` (bzw. `vorname.nachname@cardiopulse.test`), Passwort `cardiopulse`

Demo-Daten neu erzeugen: `php artisan migrate:fresh --seed`

## Funktionen

**Blutdruck-Einteilung nach ESC/ESH** (Leitlinien der European Society of Cardiology / Hypertension, wie in Österreich üblich; `app/Enums/BloodPressureCategory.php`). Systolisch und diastolisch werden einzeln eingestuft, es zählt der höhere Bereich:

| Bereich | Systolisch | | Diastolisch | Ampel |
|---|---|---|---|---|
| Hypertensive Krise | > 180 | oder | > 120 | Rot (Alarm) |
| Hypertonie Grad 3 (schwer) | ≥ 180 | oder | ≥ 110 | Rot (Alarm) |
| Hypertonie Grad 2 (mäßig) | 160–179 | oder | 100–109 | Gelb-Orange |
| Hypertonie Grad 1 (mild) | 140–159 | oder | 90–99 | Gelb-Orange |
| Hoch-normal (beobachten) | 130–139 | oder | 85–89 | Gelb-Orange |
| Normal | < 130 | und | < 85 | Grün |
| Zu niedrig (nicht Teil der ESC-Tabelle) | < 90 | oder | < 60 | Blau |

Die Ampelfarbe (`app/Enums/BloodPressureStatus.php`) ist in App und Krankenhaus identisch; die Patienten-App, Berichte und Verlauf zeigen zusätzlich den genauen Bereich mit passender Empfehlung.

### Krankenhaus

| Bereich | Funktion |
|---|---|
| **Überwachung** (D2) | Live-Board mit Triage-Sortierung Rot → Gelb-Orange → Blau (zu niedrig) → Grün, Aktualisierung alle 5 s, Hervorhebung neuer Uploads, Suche, 7-Tage-Sparkline, Hinweis auf neue Patienten-Nachrichten |
| **Alarm** (D3) | Rote Werte und die **Notfalltaste** des Patienten lösen Alarm aus – blinkendes Banner/Rahmen, Signalton (Web Audio, 960 Hz), Pflicht-Quittierung mit Maßnahme; Audit-Log. Mehrere Alarme gleichzeitig: Jeder Patient hat seinen eigenen Alarm, Notfalltaste vor hohem Messwert, ältester zuerst; **„Ich übernehme“** zeigt allen, wer sich kümmert – der Nächste bekommt automatisch den nächsten freien Alarm, der Signalton läuft, bis jeder Alarm übernommen oder quittiert ist |
| **Privacy-Lock** (D6) | Nach 3 Min Inaktivität; Namen werden serverseitig ausgeblendet, Entsperren per PIN |
| **Patienten** (D4) | 30-Tage-Verlauf, Kennzahlen, Medikation, Chat mit dem Patienten, PDF-Druckansicht, **KIS-Export als HL7 FHIR R4 Bundle** (LOINC 85354-9, 8480-6, 8462-4, 8867-4, Interpretation HH/H/N/L) |
| **Videosprechstunden** (Patientenansicht → „Termin“) | Termin vereinbaren (Datum, Uhrzeit, 10–60 Min, Arzt, interner Anlass), keine Doppelbuchung je Arzt; Patient bekommt eine E-Mail (ohne Anlass/Diagnose); absagen; ab 10 Min vor Beginn bis 30 Min nach dem Ende „Videosprechstunde starten“ (ruft den Patienten per Video an); Übersicht unter „Anrufe“ (voreingestellt: nur eigene Termine der nächsten 14 Tage; umschaltbar auf alle Ärzte und 30 Tage, 3 Monate oder alle geplanten); Erinnerungs-E-Mail an den Patienten 1 Stunde vorher (`CARDIOPULSE_REMINDER_MINUTES`, entfällt bei kurzfristig vereinbarten Terminen; braucht die geplante Aufgabe, siehe „Geplante Aufgabe“); im FHIR-Export als `Appointment` |
| **Patienten verwalten** | Anlegen (inkl. App-Zugang), Bearbeiten, Löschen mit Bestätigung |
| **Medikation** | Erfassen, Bearbeiten, Löschen; Schema morgens – mittags – abends; Änderungen durch den Patienten werden markiert |
| **Monatsberichte** | Eingegangene Berichte je Monat mit Verlauf des Berichtsmonats |
| **Anrufe / Videosprechstunde** (D5) | Arzt ↔ Patient in beide Richtungen inkl. Klingeln, Annehmen/Ablehnen, Gesprächsdauer, Gesprächsnotiz; nach dem Annehmen **Bild und Ton per WebRTC** direkt zwischen den Browsern (Ende-zu-Ende verschlüsselt), Stumm- und Kamera-Taste, eigenes Bild klein eingeblendet |
| **Ärzte** (nur Admins) | Anlegen, Bearbeiten (Profil, Benutzerkennung, Passwort, PIN, Admin-Rechte, Zwei-Faktor-Anmeldung zurücksetzen), Löschen mit Übergabe der Patienten an einen anderen Arzt; eigenes Konto, eigene Admin-Rechte und letzter Arzt sind geschützt |
| **Protokoll** (nur Admins) | Prüfprotokoll aller sicherheitsrelevanten Aktionen, neueste zuerst: Zeit, Bereich, Aktion, Benutzer, betroffener Patient/Arzt, Details, IP-Adresse; Filter nach Zeitraum, Bereich und Arzt; Export als CSV (Excel-tauglich, Schutz vor CSV-Injection; der Export wird selbst protokolliert) |
| **Mein Konto** (Klick auf den eigenen Namen oben rechts) | Eigenes Passwort und Privacy-Lock-PIN ändern, Zwei-Faktor-Anmeldung einrichten (QR-Code), Wiederherstellungscodes erneuern |

### Patienten-App

| Screen | Funktion |
|---|---|
| **Start** (M1) | Letzte Messung, Messungen von heute, Fortschritt Monatsbericht, Medikation, ungelesene Nachrichten |
| **Notfalltaste** (rote Leiste „Notfall · SOS“ oben auf jeder Seite) | Taste **2 Sekunden gedrückt halten**, danach 5 Sekunden „Abbrechen“ möglich – dann wird das Krankenhaus alarmiert; der Patient tippt auf „Notruf 144 anrufen“ (ein Browser darf nicht selbst wählen). Mit Einwilligung wird der Standort einmalig ermittelt und mitgeschickt (keine laufende Ortung, verschlüsselt gespeichert, beim Quittieren gelöscht; das Krankenhaus sieht Koordinaten und OpenStreetMap-Link). Mehrfaches Drücken erzeugt keinen zweiten Alarm. Der Patient sieht, welcher Arzt sich kümmert, und kann „Fehlalarm“ melden (der Alarm bleibt bis zur Quittierung offen) |
| **Messen** (M2–M4) | Erfassung per Numpad mit Kontext (Ruhe, Medikation, Symptome), Ergebnis in Ampelfarbe, Notfall-Interruption mit Notruf (`CARDIOPULSE_EMERGENCY_NUMBER`, Standard 144) |
| **Monat** (M5) | Monatskalender, Versand an das Krankenhaus (erneut senden möglich), PDF für den Hausarzt |
| **Arzt** (M6/M7) | Anruf an Arzt oder Zentrale, eingehende Anrufe, Videosprechstunde mit Kamera und Mikrofon des Handys, **Chat mit der Klinik** (Nachrichten lesen und beantworten, Lesebestätigung) |
| **Medikation** | Eigene Medikation erfassen, bearbeiten, löschen |
| **Termine** | Nächste Videosprechstunde auf der Startseite, alle Termine unter „Arzt“; vor Beginn selbst absagen (das Krankenhaus bekommt eine Chat-Nachricht) |
| **Mein Konto** (Link auf der Startseite) | Standort im Notfall freigeben oder widerrufen (ein Widerruf löscht auch einen schon übermittelten Standort), Passwort ändern, Abmelden |

### Löschen und Nachverfolgbarkeit

Patienten und Ärzte werden **nicht endgültig gelöscht** (Soft Delete): Die Anmeldung wird sofort gesperrt, offene Sitzungen und Anrufe werden beendet,
aber Messwerte, Alarm-Quittierungen, Anrufe und Audit-Log bleiben erhalten (Aufbewahrungspflicht § 630f BGB, Nachverfolgbarkeit nach MDR / IEC 62304).
Benutzerkennungen und E-Mail-Adressen gelöschter Konten bleiben deshalb reserviert.

Alle sicherheitsrelevanten Aktionen (Alarm quittieren, Export, Anlegen/Ändern/Löschen, Nachrichten) werden im Audit-Log (`audit_logs`) protokolliert – Admins sehen es unter „Protokoll“.

### Sicherheit

| Maßnahme | Umsetzung |
|---|---|
| Admin-Rolle | Nur Admins sehen und nutzen „Ärzte“ (anlegen, bearbeiten, löschen, Admin-Rechte vergeben). Die eigenen Admin-Rechte kann nur ein anderer Admin entziehen – so bleibt immer mindestens ein Admin |
| Zwei-Faktor-Anmeldung (Ärzte) | Nach dem Passwort ein 6-stelliger Code aus einer Authenticator-App (TOTP, RFC 6238 – Google/Microsoft Authenticator, 1Password, FreeOTP); jeder Code nur einmal; 8 Wiederherstellungscodes für den Notfall; Schlüssel verschlüsselt gespeichert; falsche Codes zählen zur Login-Sperre; nach 5 Min ohne Code beginnt die Anmeldung neu. Mit `CARDIOPULSE_REQUIRE_2FA=true` für alle Ärzte Pflicht. Ein Admin setzt sie bei verlorenem Handy zurück |
| Passwort vergessen | Link per E-Mail (60 Min gültig, nur einmal verwendbar, max. 1 Anforderung pro Minute und Konto); die Antwort verrät nicht, ob es ein Konto gibt; die E-Mail-Adresse steht nicht im Link; nach dem Zurücksetzen enden alle Sitzungen. Ärzte und Patienten haben je eine eigene Ansicht |
| Passwort-Raten | Nach 5 Fehlversuchen ist die Anmeldung für dieses Konto (je IP-Adresse) 5 Minuten gesperrt; zusätzlich max. 10 Login-Anfragen pro Minute je IP |
| Privacy-Lock-PIN | Nach 5 falschen PINs wird die Sitzung beendet; die Meldung zeigt die verbleibenden Versuche |
| Passwörter | Einheitlich mind. 8 Zeichen mit Buchstaben und Ziffern (Anlegen, Bearbeiten, eigenes Konto); beim Ändern ist das aktuelle Passwort nötig und alle anderen Sitzungen werden abgemeldet |
| PIN ändern | Passwort nötig; triviale PINs (z. B. 123456, 111111) werden abgelehnt |
| Sitzungen | Datenbank-Sessions, verschlüsselt (`SESSION_ENCRYPT=true`), Abmeldung nach 120 Min ohne Anfrage |
| HTTP-Header | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`; HSTS bei HTTPS |
| HTTPS | Im Produktivbetrieb (`APP_ENV=production`) werden alle Links auf HTTPS erzeugt |
| Audit-Log | Anmeldungen, Fehlversuche, Sperren, Passwort- und PIN-Änderungen, angeforderte und durchgeführte Passwort-Zurücksetzungen werden protokolliert |

Validierungsmeldungen und E-Mails sind deutsch (`lang/de/validation.php`, `lang/de/passwords.php`, `lang/de.json`).

### Demo-Modus

„Demo: Upload simulieren" auf dem Board (`CARDIOPULSE_DEMO=false` zum Abschalten).

## Bewusste Vereinfachungen / offene Punkte

- Echtzeit per **Polling** (5 s) statt WebSockets – Austausch gegen Laravel Reverb vorgesehen.
- **Videosprechstunde (WebRTC):** Der Verbindungsaufbau läuft über die App (Polling, `call_signals`, verschlüsselt, nach dem Auflegen gelöscht); Bild und Ton gehen direkt zwischen den Browsern (DTLS-SRTP). Der Arzt-Browser bietet an, der Patienten-Browser antwortet; nach einem Neuladen verbindet sich das Gespräch selbst neu. STUN: `stun.nextcloud.com` (Deutschland). In sehr abgeschotteten Netzen (Firmen-WLAN, manche Mobilfunknetze) braucht es zusätzlich einen **TURN-Server** (`CARDIOPULSE_TURN_*`, z. B. coturn auf einem kleinen VPS) – ohne ihn zeigt die App nach 25 s den Hinweis, über die Telefonnummer zu telefonieren. Ohne Kamera-Freigabe sieht und hört man die Gegenseite trotzdem.
- FHIR `Encounter` fehlt noch; der FHIR-Export ist ein Download, keine REST-API mit SMART on FHIR / OAuth 2.0.
- **Notfalltaste:** In der Web-App wird der Standort nur ermittelt, solange die App offen ist (Browser-Ortung mit Freigabe am Handy) – kein Hintergrund-Tracking, das ginge nur mit einer nativen App. Ebenso kann nur eine native App den Notruf ohne Tippen wählen.
- Die **Privacy-Lock-PIN** lässt sich nicht per E-Mail zurücksetzen – ein Admin setzt sie unter „Ärzte“ neu.
- Der Chat ist **kein Notfallkanal** – die App weist auf den Notruf hin (Standard **144**, Rettung Österreich; in Deutschland `CARDIOPULSE_EMERGENCY_NUMBER=112`).
- Bluetooth-Import und Foto-Scan (OCR) sind nur in nativen Apps sinnvoll – in der Web-App erscheint ein Hinweis.
- Hypotonie wird **blau** dargestellt („Blue Ice“ laut Konzept, für WCAG-AA-Kontrast mit weißer Schrift leicht abgedunkelt: `#0277BD` statt `#0288D1`). Das Mockup sah weiß vor – das war zu unauffällig.
- Die Grenzwerte folgen den ESC/ESH-Leitlinien (siehe oben). Rot beginnt bei Hypertonie Grad 3 (≥ 180 / ≥ 110) – das Konzept sah ≥ 180 / ≥ 120 vor; bestehende Messungen mit diastolisch 110–119 wurden umgefärbt (ohne nachträgliche Alarme).
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
| `MAIL_*` | Postfach für „Passwort vergessen“, siehe unten |
| `CARDIOPULSE_REQUIRE_2FA` | `true`, sobald alle Ärzte ein Handy mit Authenticator-App haben – dann muss jeder die Zwei-Faktor-Anmeldung einrichten |

Gibt es eine Zeile doppelt, gilt die untere – geänderte Werte daher direkt in der vorhandenen Zeile eintragen.

Auf dem eigenen PC `upload-assets.bat` ausführen, dann auf dem Server installieren und den ersten Arzt-Zugang anlegen (**keine Demo-Daten** – der erste Arzt wird automatisch Admin und legt alle weiteren Ärzte und Patienten in der App an):

```bash
./deploy.sh
php artisan cardiopulse:create-doctor
```

Zum Schluss im Panel den **Document Root** der Domain auf `/cardio-pulse/public` setzen.

Ist kein Admin mehr erreichbar, vergibt `php artisan cardiopulse:make-admin <benutzerkennung>` die Admin-Rechte auf dem Server; `php artisan cardiopulse:reset-two-factor <benutzerkennung>` setzt die Zwei-Faktor-Anmeldung zurück (Handy und Wiederherstellungscodes verloren).

### E-Mail (Passwort vergessen)

Im netcup-Panel ein Postfach anlegen (z. B. `noreply@caryssa.at`) und in der `.env` eintragen. Server-Name und Port stehen im Panel bei den E-Mail-Einstellungen:

```ini
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=<SMTP-Server laut netcup-Panel>
MAIL_PORT=465
MAIL_USERNAME=noreply@caryssa.at
MAIL_PASSWORD=<Passwort des Postfachs>
MAIL_FROM_ADDRESS=noreply@caryssa.at
```

Danach `php artisan optimize:clear`. Die E-Mails werden sofort gesendet (kein Queue-Worker nötig). Ist der Mailserver nicht erreichbar, steht der Fehler in `storage/logs/laravel.log` – der Besucher sieht dieselbe Meldung wie immer.

### Updates

1. Änderungen auf GitHub pushen, dann auf dem eigenen PC `upload-assets.bat` ausführen (nur nötig, wenn sich CSS/JS geändert hat; warnt, wenn der lokale Stand von GitHub abweicht).
2. Auf dem Server:

   ```bash
   cd cardio-pulse && ./deploy.sh
   ```

`deploy.sh` schaltet die App in den Wartungsmodus, holt den neuesten Stand von GitHub, gleicht die PHP-Pakete ab (lädt Composer als `composer.phar`, falls der Befehl fehlt), baut die Assets (nur mit Node.js 20.19+ / 22.12+), führt Migrationen aus, leert die Laravel-Caches und bricht beim ersten Fehler ab. Eine andere PHP-Version als den Shell-Standard nutzt `PHP=/usr/local/php83/bin/php ./deploy.sh`. Konfiguration, Routen und Views werden bewusst nicht gecacht: Die netcup-SSH-Shell sieht das Projekt unter `/cardio-pulse`, der Webserver unter `/var/www/vhosts/…/cardio-pulse` – ein Cache aus der Shell enthielte falsche Pfade.

### Datensicherung

`backup.bat` (Doppelklick auf dem eigenen PC) legt auf dem Server mit `php artisan cardiopulse:backup` einen Schnappschuss der Datenbank an (`VACUUM INTO` – in sich stimmig, auch während Messwerte eintreffen) und lädt ihn zusammen mit der `.env` (enthält `APP_KEY`) als `backups\cardiopulse-JJJJ-MM-TT_HHMM.tar.gz` herunter. Der Ordner `backups\` ist nicht in Git.

Auf dem Server bleiben die neuesten 14 Schnappschüsse in `storage/app/backups/`. Ist die geplante Aufgabe eingerichtet (siehe unten), sichert der Server zusätzlich jede Nacht um 02:00 Uhr selbst.

**Wiederherstellen:** Archiv entpacken, die `.sqlite`-Datei als `database/database.sqlite` und die `.env` auf den Server kopieren, dann `./deploy.sh`.

Die Sicherungen enthalten Gesundheitsdaten – verschlüsselt aufbewahren (z. B. BitLocker-USB-Stick), nicht per E-Mail versenden.

### Geplante Aufgabe (Terminerinnerung, nächtliche Sicherung)

Laravel erledigt zeitgesteuerte Arbeiten über `php artisan schedule:run` (festgelegt in `routes/console.php`):

| Aufgabe | Wann |
|---|---|
| `cardiopulse:send-reminders` – Erinnerungs-E-Mail vor Videosprechstunden | alle 5 Minuten |
| `cardiopulse:backup` – Schnappschuss der Datenbank | täglich 02:00 Uhr |

Einmalig im netcup-Panel (Plesk) unter **Websites & Domains → Geplante Aufgaben → Aufgabe hinzufügen**:

- Aufgabentyp: **Befehl ausführen**
- Befehl: `cd /cardio-pulse && /usr/local/php83/bin/php artisan schedule:run`
- Ausführen: **Cron-Stil** `*/5 * * * *`
- Benachrichtigung: nur bei Fehlern

Mit **„Jetzt ausführen“** testen – die Ausgabe nennt die gestarteten Aufgaben oder „No scheduled commands are ready to run.“. Von Hand: `php artisan cardiopulse:send-reminders`.

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
