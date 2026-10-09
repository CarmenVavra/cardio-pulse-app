@php($emergency = config('cardiopulse.emergency_number'))
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F2C59">
    <title>Keine Verbindung · CardioPulse</title>
    {{-- Eigenständig (ohne externe Dateien), weil der Service Worker nur diese Seite für den Offline-Fall speichert. --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; justify-content: center; gap: 18px; padding: 28px 22px; background: #0f2c59; color: #eae2d6; font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        h1 { margin: 0; font-size: 26px; line-height: 1.2; color: #fff; }
        p { margin: 0; }
        a { display: flex; align-items: center; justify-content: center; min-height: 64px; padding: 0 20px; font-size: 22px; font-weight: 800; text-decoration: none; }
        .call { background: #d32f2f; color: #fff; }
        .retry { border: 2px solid #eae2d6; color: #eae2d6; font-size: 17px; min-height: 52px; }
        a:focus-visible { outline: 3px solid #e1b80d; outline-offset: 3px; }
    </style>
</head>
<body>
    <h1>Keine Internetverbindung</h1>
    <p>CardioPulse braucht Internet, um Ihre Werte zu senden und Ihr Behandlungsteam zu erreichen. Bitte prüfen Sie WLAN oder mobile Daten.</p>
    <p><b>Im Notfall rufen Sie sofort den Notruf an</b> – das funktioniert auch ohne Internet.</p>
    <a class="call" href="tel:{{ $emergency }}">Notruf {{ $emergency }} anrufen</a>
    <a class="retry" href="{{ route('patient.home') }}">Erneut versuchen</a>
</body>
</html>
