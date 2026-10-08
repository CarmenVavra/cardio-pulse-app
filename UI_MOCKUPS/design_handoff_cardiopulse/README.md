# Handoff: CardioPulse – Krankenhaus-Überwachungsscreen & Patienten-App

## Overview
CardioPulse is a telemedicine platform (SaMD, EU-MDR Class IIa) for patients with arterial hypertension who live **at home**. Patients log blood pressure several times a day in a mobile app and send a monthly report to the hospital. Every upload appears instantly, with the patient's name, on a 24/7 monitoring screen in the hospital, colour-coded with the same traffic-light colours the patient sees. Dangerously high values trigger a blinking banner and a repeating alarm tone on the hospital screen until staff acknowledge it. Doctor and patient can phone each other through the app.

Two products:
- **Hospital dashboard** – desktop web app, designed at 1440 × 900 (screens D1–D6)
- **Patient app** – iOS/Android, designed at 390 × 844 (screens M1–M7)

## About the Design Files
The files in this bundle are **design references created in HTML** – prototypes showing intended look and behaviour, not production code. The task is to **recreate these designs in the target codebase's environment** (e.g. React/Next.js for the dashboard, React Native / Flutter / SwiftUI for the app) using its established patterns. If no environment exists yet, choose the most appropriate stack. Suggested: React + TypeScript for the dashboard, React Native or Flutter for the app; backend via HL7 FHIR R4 (REST + WebSockets), see `reference/CardioPulse_Projektkonzept.pdf`.

Open `CardioPulse Mockups.dc.html` in a browser (served from this folder) to view all screens on one canvas. The screen markup is inside the file between `<x-dc>` tags; mock data and logic are in the `class Component` script at the bottom.

## Fidelity
**High-fidelity.** Final colours, typography, spacing and copy (German). Recreate pixel-accurately. Data is mock data.

## Design Language (Modernist)
- Flat, architectural, visible grid. **Border-radius 0 everywhere** (device frame corners are mockup chrome only).
- Strong **2px rules** `#0F2C59` between major sections; 1px `#D5C3AA` between list rows.
- **Labels flush left**, including inside buttons (trailing arrow/icon right-aligned with `justify-content: space-between`). Never centre button labels.
- Font: **Archivo** (Google Fonts, weights 400/600/800). Headings 800, letter-spacing −0.01 to −0.03em.
- Icons: **Lucide** (stroke 2–2.6, 14–28px): phone, video, message-square, bell, lock, search, chevron, plus, upload, download, calendar, home, activity, alert-triangle, check, volume-2, arrow-right.
- Uppercase micro-labels: 11–12px, weight 600–800, letter-spacing .08–.12em, colour `#5C5045`.

## Design Tokens

### Brand (user palette + concept)
| Token | Hex | Use |
|---|---|---|
| ground | `#EAE2D6` | App/page background |
| surface | `#F7F3ED` | Cards, list rows, inputs |
| sand | `#D5C3AA` | Secondary fills, disclaimer banner, chat bubbles (incoming), 1px row dividers |
| taupe | `#867666` | Secondary strokes, placeholder text, diastolic line |
| muted-text | `#5C5045` | Secondary text (AA on ground) |
| **cardio-yellow** | `#E1B80D` | The word "Cardio" in the logo, primary action buttons, active tab indicator |
| ink / navy | `#0F2C59` | Text, nav bars, 2px rules, secondary buttons |
| cyan | `#00A8CC` | LIVE indicator, focus ring (2px), audio waveform |
| device chrome | `#14130F` | Phone bezel only |

**Logo:** "CardioPulse" in Archivo 800 – "Cardio" in `#E1B80D`, "Pulse" in `#EAE2D6` (on navy) or `#0F2C59` (on light). Prefer placing the logo on navy: yellow on cream has too little contrast.

### Traffic light (identical in app and hospital)
| Status | Rule (sys / dia mmHg) | Fill | Tint (tag bg) | Text on tint |
|---|---|---|---|---|
| Rot – gefährlich hoch | sys ≥ 180 **or** dia ≥ 120 | `#D32F2F` | `#D32F2F` (tag text white); row bg `#FBEDEB`; light tint `#F6DCD8` | `#B71C1C` |
| Gelb-Orange – zu hoch | sys ≥ 130 **or** dia ≥ 85 | `#ED6C02` | `#FBE3CF` | `#9A4600` |
| Grün – normal | otherwise | `#2E7D32` | `#DCEADB` | `#1F5A23` |
| Weiß – zu niedrig | sys < 90 **or** dia < 60 | `#FFFFFF` + **2px inset outline `#0F2C59`** | `#FFFFFF` + outline | `#0F2C59` |

Evaluation order: red → white (low) → amber → green.
```ts
const status = (s, d) => s >= 180 || d >= 120 ? 'red' : (s < 90 || d < 60) ? 'white' : (s >= 130 || d >= 85) ? 'amber' : 'green';
```
Note: the concept leaves 130–139 / 85–89 undefined; it's treated as amber here – **confirm with the medical lead.**
Sort order on hospital lists: red → amber → green → white.

### Type scale (px)
Display 72–96 (call timer, login hero 84) · BP hero 58–80 (app result 76–80, dashboard 64) · H1 40–52 · H2 28–32 · stat number 36–44 · BP in table 20–22 · body 14–17 · meta 12–13 · micro-label 11.

### Spacing
4 / 6 / 8 / 10 / 12 / 14 / 16 / 20 / 24 / 28 / 56. Desktop page padding 24 (header) / 28 (content). Mobile padding 16, header 22.

### Elevation
Flat; only modals: `0 30px 80px rgba(0,0,0,.4)` over a `rgba(15,44,89,.74)` backdrop.

## Screens – Hospital (Desktop 1440 × 900)

All desktop screens share:
- **Top bar** 56px, navy; logo 20px/800 left; tabs (Überwachung, Patienten, Monatsberichte, Anrufe) 14px, active = white 600 + 3px yellow bottom border; right side: context label, cyan 8px square + "LIVE hh:mm:ss", avatar 30px yellow square with initials + name.
- **Disclaimer banner** at the bottom (always visible): bg `#D5C3AA`, 2px top rule, 12px text: "**Haftungsausschluss:** CardioPulse ist ein sekundäres Assistenzsystem zur begleitenden Verlaufskontrolle (MDR Klasse IIa) – keine primäre Echtzeit-Intensivüberwachung."

### D1 – Anmeldung
2-column grid. Left navy panel (padding 56): logo, kicker "Krankenhaus · Überwachung", hero "Messen. / Übertragen. / Schützen." (84px/800, last line yellow), slogan, 3-column footer of compliance labels. Right (padding 56/96): "Anmelden" 40px; fields Benutzerkennung, Passwort (focused: 2px cyan border + outline), Abteilung (select); checkbox "Signalton für diesen Bildschirm aktivieren" (**required for audio – browsers only allow sound after a user gesture; use this click to unlock the AudioContext**); primary button 56px yellow "Anmelden und Überwachung starten →".

### D2 – Überwachungsscreen (main live board)
Vertical stack:
1. Top bar.
2. **Alarm banner** (only while an unacknowledged red value exists): 56px, bg `#D32F2F`, white. Blinking 14px white square (1s, steps(1), opacity .1 at 50%), "GEFÄHRLICH HOHER WERT", patient name · value · symptom · time. Right: outlined button "Signalton an / Ton aus" (toggles tone) and white button "Alarm quittieren →" (text `#B71C1C`).
   After acknowledge → banner becomes bg `#F6DCD8`, 2px bottom border red: "Alarm quittiert · Name · durch Dr. … · hh:mm".
3. **Counter strip**: 5-column grid (4 equal + 1.3fr), each cell has a 6px top border in its status colour (white cell: white with navy inset line), label + 44px count. Last cell: search field (42px, 2px navy border) + "Heute 38 Uploads · 11 Patienten zu Hause".
4. **Table header** 38px, micro-labels; columns `10px | 150 | 1.5fr | 160 | 70 | 120 | 1.3fr | 150 | 160`: stripe, Ampel, Patient, RR mmHg, Puls, 7 Tage, Symptome, Upload, action.
5. **Rows** 52px: colour stripe (10px), status tag, name 16/600 + "68 J. · zu Hause · Hamburg" 12px, BP 22/800 in status text colour, pulse, 7-day sparkline (100×28 SVG, status colour), symptoms (ellipsis), upload time (bold) + type ("Einzelmessung" / "Monatsbericht"), call button 34px ("Sofort anrufen" red fill white text for red rows, otherwise "Anrufen" outlined). Red rows bg `#FBEDEB`.
6. Disclaimer.

**New uploads** arrive via WebSocket and are inserted at their sorted position (consider a brief highlight). Each row shows the patient's **name** (except in Privacy-Lock).

### D3 – Alarm (modal)
Board dimmed by navy 74% overlay; a 12px red frame around the viewport blinks. Centred modal 660px wide:
- Red header: alert-triangle icon, "GEFÄHRLICH HOHER BLUTDRUCK", "Signalton aktiv – wiederholt bis zur Quittierung", elapsed timer "seit 00:02:14".
- 2-col body: name 26/800, "68 J. · CP-10482 · zu Hause", home address + phone, symptom tags (`#F6DCD8` / `#B71C1C`); right: "Upload 08:40", BP 58/800 red, pulse.
- Textarea "Maßnahme dokumentieren".
- Buttons (2 cols, 54px): "Alarm quittieren ✓" (red) and "Patient anrufen" (yellow).
- Footer note (sand): patient is at home; dispatch emergency services (112) to the home address if needed; acknowledgement is logged.

### D4 – Patientendetail / Monatsbericht
Columns 330px | rest. Left: list of patients with received monthly reports (8px stripe, name, ID · time, BP); selected row bg `#D5C3AA`. Right:
- Header: status tag, name 32/800, meta line; buttons "Anrufen" (yellow), "Nachricht", "PDF / KIS-Export" (outlined), 44px.
- 4 stat cells: last value, Ø 30 days, number of measurements, distribution bar (red/amber/green/white flex segments in 2px navy frame).
- **30-day chart** (1000×250): background bands (red ≥180 `#F6DCD8`, amber 130–180 `#FBE3CF`, low <90 white), dashed amber 130 line, systolic line navy 2px, diastolic taupe 2px, every measurement as a square dot in its status colour. Multiple measurements per day.
- Bottom 2 cols: "Letzte Uploads" list and "Medikation".

### D5 – Telefonat Arzt ↔ Patient
Columns 1fr | 460px. Left navy: "LAUFENDES GESPRÄCH · VERSCHLÜSSELT" (yellow), name 72/800, call info, timer 96/800 tabular-nums, cyan audio level bars; controls (60px, outlined taupe): Stumm, Lautsprecher, Halten; "Auflegen →" red. Right surface: today's values list and a note field + "Notiz speichern" (yellow).

### D6 – Privacy-Lock
After 3 min of inactivity: left navy panel with lock icon, "Bildschirm gesperrt", explanation, 6-digit PIN boxes (56px), "PIN eingeben oder Klinikausweis vorhalten". Right: the board keeps running, **names are masked** (sand bar), patient ID, status and BP stay visible; alarms and the tone keep running.

## Screens – Patient App (390 × 844)
Shared: navy header with status bar, logo / title 20–24px/800. Bottom tab bar 78px (4 tabs: Start, Messen, Monat, Arzt), active tab = 4px yellow top border, inactive `#5C5045`. Hit targets ≥ 44px; primary buttons 56–72px.

- **M1 Start** – greeting "Guten Tag, Josef", connection status (green square + "Verbunden mit Klinikum Nord · K3"). Last-measurement card (8px top border in status colour, status tag, BP 64/800, pulse). Primary "+ Blutdruck eintragen". "Heute · 3 Messungen" list (stripe + time + BP coloured). "Monatsbericht Oktober" progress: 31 small cells coloured per day logged (sand = not yet), "Versand an das Krankenhaus ab 31.10. möglich".
- **M2 Blutdruck eintragen** – segmented: Eintippen / Bluetooth / Foto-Scan. Three value fields OBERER / UNTERER / PULS (active = 3px cyan border), numpad 3×4 (1–9, ⌫, 0, →), symptom chips (Kopfschmerz, Schwindel, Brustdruck, Keine – selected = navy fill), "Speichern →".
- **M3 Ergebnis (Gelb-Orange example)** – the full header takes the status colour (red/amber/green; for white use white with navy text + 2px rule). Kicker "IHR ERGEBNIS · hh:mm", status word 52px, BP 80px. Scale bar of 4 segments (Zu niedrig / Normal / Zu hoch / Gefährlich) with a navy marker. Advice text, confirmation "Gespeichert & ans Krankenhaus übertragen – erscheint dort in derselben Farbe". Buttons: "Erinnerung in 5 Min →", "Zur Startseite".
- **M4 Ergebnis Rot (safety interruption)** – full red screen, white text: "GEFÄHRLICH HOHER WERT", BP 76px, symptom, emergency advice; white 72px button "Notruf 112" (red text, phone icon) → `tel:112`; outlined "Meinen Arzt anrufen"; "✓ Das Krankenhaus wurde sofort benachrichtigt"; checkbox "Kein Brustschmerz, keine Atemnot"; "Ich habe keine Beschwerden" (only enabled after checkbox).
- **M5 Monatsübersicht** – month header with ‹ ›; calendar 7 columns (MO–SO), 40px cells coloured by the **highest-severity value of the day** (text white on red/green, navy on amber/white; white cells outlined). Stats: Messungen, Durchschnitt, Im Normalbereich %. Notice "Ihr Monatsbericht ist bereit…", primary "An Krankenhaus senden" (upload icon), secondary "Als PDF für Hausarzt speichern".
- **M6 Arzt kontaktieren** – doctor card (initials, name, availability) with yellow "Anrufen"; "Telemedizin-Zentrale – rund um die Uhr" with outlined "Anrufen"; recent calls list; note "Im Notfall immer zuerst 112 wählen".
- **M7 Eingehender Anruf** – full navy: "EINGEHENDER ANRUF" (yellow), "Dr. Miriam Weber" 46px, context ("Bezug: Ihre Messung von 08:40 · 192/124"), green "Annehmen" 64px, red-outlined "Ablehnen".

## Interactions & Behavior
- **Upload flow:** save measurement (M2) → classify → show M3 or M4 → POST FHIR `Observation` (LOINC 85354-9, components 8480-6 / 8462-4, pulse 8867-4, `interpretation` e.g. HH for red) → hospital board receives via WebSocket and inserts the row.
- **Monthly report:** "An Krankenhaus senden" bundles the month's Observations; the row on the board shows type "Monatsbericht".
- **Red alarm (hospital):** blink 1s `steps(1)` (opacity 1 → .1); tone = two short 960 Hz square beeps (180ms, 250ms apart) repeating every 1.2s via Web Audio, gain ~0.12. Plays until "Alarm quittieren"; acknowledgement requires user + timestamp (+ optional action note) and is written to an audit log. Prototype: "Signalton an" in the D2 banner plays the real tone; "Alarm quittieren" stops it; "Demo: Alarm erneut auslösen" resets.
- **Red value (patient):** M4 interrupts the flow; the hospital is notified automatically.
- **Calls:** VoIP/WebRTC in both directions (doctor → patient: D2/D4 "Anrufen", M7 incoming; patient → doctor: M6). D5 shows the in-call screen.
- **Privacy-Lock:** 3 min inactivity → D6; unlock via PIN or badge.
- Hover: outlined buttons → `#D5C3AA` tint; filled buttons darken one step. Focus: `outline: 2px solid #00A8CC; outline-offset: 2px`.

## State (suggested)
- Hospital: `patients[]` (id, name, age, town, address, phone, latest {sys, dia, pulse, time, kind, symptoms}, status), `activeAlarms[]` (patientId, since, acknowledged, ackBy, ackAt, note), `soundUnlocked`, `soundOn`, `locked`, `selectedPatientId`, `call` (state, peer, startedAt).
- Patient: `measurements[]` (sys, dia, pulse, time, symptoms, method), `currentInput`, `monthlyReport` (month, sentAt), `connection`, `incomingCall`.

## Assets
- No raster images. Icons: Lucide. Font: Archivo (Google Fonts).
- `reference/farbpalette.png` – the client's colour palette.
- `reference/CardioPulse_Projektkonzept.pdf` – the product/regulatory concept (FHIR mapping, MDR notes).

## Files
- `CardioPulse Mockups.dc.html` – all screens (A: D1–D6 desktop, B: M1–M7 mobile) + legend; mock data/logic in the bottom `<script>`.
- `support.js` – runtime needed to open the HTML prototype locally.
- `_ds/…/styles.css` – Modernist base stylesheet (font import, base resets).
