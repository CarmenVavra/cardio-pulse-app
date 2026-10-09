<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Klinik
    |--------------------------------------------------------------------------
    */

    'clinic' => [
        'name' => 'Klinikum Nord',
        'ward' => 'Kardiologie K3',
        'hotline' => '040 1234 5670',
        'hotline_label' => 'Telemedizin-Zentrale',
    ],

    'departments' => [
        'telemonitoring' => 'Kardiologie · Telemonitoring',
        'k3' => 'Kardiologie K3 · Station',
        'notaufnahme' => 'Zentrale Notaufnahme',
        'innere' => 'Innere Medizin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Überwachungsscreen
    |--------------------------------------------------------------------------
    |
    | privacy_lock_seconds: Inaktivität bis zum Privacy-Lock (Anonym-Modus).
    | poll_interval_ms: Aktualisierungsintervall des Live-Boards.
    |
    */

    'privacy_lock_seconds' => (int) env('CARDIOPULSE_PRIVACY_LOCK_SECONDS', 180),

    'poll_interval_ms' => (int) env('CARDIOPULSE_POLL_INTERVAL_MS', 5000),

    /*
    | Demo-Modus: Schaltfläche "Demo: Upload simulieren" auf dem Überwachungsscreen.
    */

    'demo' => (bool) env('CARDIOPULSE_DEMO', true),

    /*
     | Reverse-Proxy vor der App (z. B. Load Balancer beim Hosting): IP-Adressen, deren
     | X-Forwarded-*-Header vertraut wird, kommagetrennt oder "*". Leer = kein Proxy.
     | Nötig, damit HTTPS und die Client-IP (Login-Sperre, Audit-Log) korrekt erkannt werden.
     */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
     | Notrufnummer in Patienten-App und Alarm (Österreich: 144 Rettung, Deutschland: 112).
     */
    'emergency_number' => env('CARDIOPULSE_EMERGENCY_NUMBER', '144'),

    /*
     | Videosprechstunde (WebRTC): Bild und Ton laufen direkt zwischen den Browsern.
     | STUN hilft beim Finden der öffentlichen Adresse (Standard: Nextcloud, Deutschland –
     | sieht nur die IP-Adresse, keine Inhalte). TURN leitet weiter, wenn keine direkte
     | Verbindung möglich ist (z. B. Firmen-WLAN) – braucht einen eigenen Server, optional.
     */
    'video' => [
        'stun' => env('CARDIOPULSE_STUN_URLS', 'stun:stun.nextcloud.com:443'),
        'turn_url' => env('CARDIOPULSE_TURN_URL'),
        'turn_username' => env('CARDIOPULSE_TURN_USERNAME'),
        'turn_credential' => env('CARDIOPULSE_TURN_CREDENTIAL'),
    ],

    /*
     | Terminerinnerung: So viele Minuten vor einer Videosprechstunde bekommt der Patient
     | eine E-Mail. Verschickt von `php artisan schedule:run` (geplante Aufgabe am Server,
     | alle 5 Minuten); 0 schaltet die Erinnerung ab.
     */
    'appointment_reminder_minutes' => (int) env('CARDIOPULSE_REMINDER_MINUTES', 60),

    /*
     | Zwei-Faktor-Anmeldung für Ärzte: Bei true muss jeder Arzt sie nach der Anmeldung
     | einrichten, bevor er die App nutzen kann (empfohlen im Produktivbetrieb).
     */
    'require_two_factor' => (bool) env('CARDIOPULSE_REQUIRE_2FA', false),

    /*
    |--------------------------------------------------------------------------
    | Erfassung
    |--------------------------------------------------------------------------
    */

    'symptoms' => [
        'kopfschmerz' => 'Kopfschmerz',
        'schwindel' => 'Schwindel',
        'brustdruck' => 'Brustdruck',
        'atemnot' => 'Atemnot',
        'muedigkeit' => 'Müdigkeit',
    ],

    'methods' => [
        'manual' => 'Eintippen',
        'bluetooth' => 'Bluetooth',
        'photo' => 'Foto-Scan',
    ],

    'disclaimer' => 'CardioPulse ist ein sekundäres Assistenzsystem zur begleitenden Verlaufskontrolle (MDR Klasse IIa) – keine primäre Echtzeit-Intensivüberwachung.',

];
