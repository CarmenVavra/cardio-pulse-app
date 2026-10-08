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
