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

Telemedizin-Plattform für Hypertonie-Patienten zu Hause: Patienten-App (Smartphone) + 24/7-Überwachungsscreen im Krankenhaus.
Umsetzung nach `CardioPulse_Projektkonzept.pdf` und den Mockups in `UI_MOCKUPS/` (Screens D1–D6, M1–M7).

**Stack:** Laravel 12 · Blade · Eloquent · SQLite · Vite (Vanilla JS/CSS, Modernist-Design) · PHPUnit · Pint · Larastan

## Starten / Stoppen

- `start.bat` – installiert bei Bedarf Abhängigkeiten, legt DB mit Demo-Daten an, baut Assets und startet den Server auf **http://127.0.0.1:8700**
- `stop.bat` – beendet den Server auf Port 8700

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

- **Ampel** (identisch App/Krankenhaus): Rot ≥180/≥120 · Weiß <90/<60 · Gelb-Orange ≥130/≥85 · Grün sonst (`app/Enums/BloodPressureStatus.php`)
- **Live-Board** (D2): Triage-Sortierung Rot → Gelb-Orange → Grün → Weiß, Aktualisierung alle 5 s, neue Uploads werden hervorgehoben, Suche, 7-Tage-Sparkline
- **Alarm** (D3): rote Werte lösen Alarm aus – blinkendes Banner/Rahmen, Signalton (Web Audio, 960 Hz) bis zur Pflicht-Quittierung mit Maßnahme; Audit-Log
- **Privacy-Lock** (D6): nach 3 Min Inaktivität, Namen werden serverseitig ausgeblendet, Entsperren per PIN
- **Patientendetail / Monatsberichte** (D4): 30-Tage-Verlauf, Kennzahlen, Medikation, Nachricht an Patient, PDF-Druckansicht, **KIS-Export als HL7 FHIR R4 Bundle** (LOINC 85354-9, 8480-6, 8462-4, 8867-4, Interpretation HH/H/N/L)
- **Anrufe** (D5/M6/M7): Arzt ↔ Patient in beide Richtungen inkl. Klingeln, Annehmen/Ablehnen, Gesprächsdauer, Gesprächsnotiz
- **Patienten-App**: Erfassung per Numpad (M2), Ergebnis (M3), Notfall-Interruption mit Notruf 112 (M4), Monatskalender + Versand an Krankenhaus + PDF für Hausarzt (M5)
- **Demo-Modus**: „Demo: Upload simulieren" auf dem Board (`CARDIOPULSE_DEMO=false` zum Abschalten)

## Bewusste Vereinfachungen / offene Punkte

- Echtzeit per **Polling** (5 s) statt WebSockets – Austausch gegen Laravel Reverb vorgesehen.
- Anrufe: Signalisierung und Status sind umgesetzt, der **Sprachkanal (WebRTC/VoIP)** ist nicht angebunden.
- Bluetooth-Import und Foto-Scan (OCR) sind nur in nativen Apps sinnvoll – in der Web-App erscheint ein Hinweis.
- Hypotonie wird gemäß Mockup **weiß mit dunkler Kontur** dargestellt (Konzept nennt „Blue Ice #0288D1").
- Bereich 130–139 / 85–89 ist im Konzept undefiniert und wird als Gelb-Orange gewertet – **mit medizinischer Leitung abstimmen**.
- Mikro-Labels sind 12 px statt 11 px (Mindestschriftgröße laut CLAUDE.md).

## Qualitätssicherung

```bash
php artisan test
./vendor/bin/pint
./vendor/bin/phpstan analyse
npm run build
```

Laravel Boost ist installiert; der MCP-Server ist in `.mcp.json` registriert (`php artisan boost:mcp`).
