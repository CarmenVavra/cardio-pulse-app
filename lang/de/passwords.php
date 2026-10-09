<?php

/*
|--------------------------------------------------------------------------
| Passwort zurücksetzen (Meldungen des Password-Brokers)
|--------------------------------------------------------------------------
|
| „sent“, „throttled“ und „user“ zeigt die App bewusst nicht an: Sonst ließe
| sich herausfinden, ob es zu einer E-Mail-Adresse ein Konto gibt.
|
*/

return [
    'reset' => 'Ihr Passwort wurde geändert. Bitte melden Sie sich mit dem neuen Passwort an.',
    'sent' => 'Falls es ein Konto mit dieser E-Mail-Adresse gibt, haben wir Ihnen einen Link zum Zurücksetzen gesendet.',
    'throttled' => 'Bitte warten Sie kurz, bevor Sie es erneut versuchen.',
    'token' => 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.',
    'user' => 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.',
];
