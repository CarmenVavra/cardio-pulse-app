<?php

/*
|--------------------------------------------------------------------------
| Validierungsmeldungen (Deutsch)
|--------------------------------------------------------------------------
| Standardtexte für alle Prüfregeln. Feldnamen kommen aus den attributes()
| der Form Requests bzw. aus 'attributes' unten. Spezielle Meldungen stehen
| weiterhin in den messages() der Form Requests.
*/

return [

    'accepted' => 'Bitte bestätigen Sie :attribute.',
    'after' => ':Attribute muss nach dem :date liegen.',
    'after_or_equal' => ':Attribute muss am oder nach dem :date liegen.',
    'alpha_dash' => ':Attribute darf nur Buchstaben, Ziffern, Binde- und Unterstriche enthalten.',
    'array' => ':Attribute muss eine Liste sein.',
    'before' => ':Attribute muss vor dem :date liegen.',
    'before_or_equal' => ':Attribute muss am oder vor dem :date liegen.',
    'between' => [
        'array' => ':Attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':Attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':Attribute muss zwischen :min und :max liegen.',
        'string' => ':Attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':Attribute muss ja oder nein sein.',
    'confirmed' => 'Die Wiederholung stimmt nicht mit dem Feld „:attribute“ überein.',
    'current_password' => 'Das Passwort ist nicht korrekt.',
    'date' => ':Attribute ist kein gültiges Datum.',
    'date_format' => ':Attribute muss das Format :format haben.',
    'different' => ':Attribute und :other müssen sich unterscheiden.',
    'digits' => ':Attribute muss aus genau :digits Ziffern bestehen.',
    'digits_between' => ':Attribute muss aus :min bis :max Ziffern bestehen.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'gt' => [
        'array' => ':Attribute muss mehr als :value Einträge haben.',
        'file' => ':Attribute muss größer als :value Kilobyte sein.',
        'numeric' => ':Attribute muss größer als :value sein.',
        'string' => ':Attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':Attribute muss mindestens :value Einträge haben.',
        'file' => ':Attribute muss mindestens :value Kilobyte groß sein.',
        'numeric' => ':Attribute muss mindestens :value sein.',
        'string' => ':Attribute muss mindestens :value Zeichen lang sein.',
    ],
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'lt' => [
        'array' => ':Attribute muss weniger als :value Einträge haben.',
        'file' => ':Attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => ':Attribute muss kleiner als :value sein.',
        'string' => ':Attribute muss kürzer als :value Zeichen sein.',
    ],
    'lowercase' => ':Attribute darf nur Kleinbuchstaben enthalten.',
    'max' => [
        'array' => ':Attribute darf höchstens :max Einträge haben.',
        'file' => ':Attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':Attribute darf höchstens :max sein.',
        'string' => ':Attribute darf höchstens :max Zeichen lang sein.',
    ],
    'min' => [
        'array' => ':Attribute muss mindestens :min Einträge haben.',
        'file' => ':Attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen lang sein.',
    ],
    'not_in' => 'Die Auswahl für :attribute ist ungültig.',
    'numeric' => ':Attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':Attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':Attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':Attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':Attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':Attribute ist in einem Datenleck aufgetaucht. Bitte wählen Sie ein anderes.',
    ],
    'regex' => ':Attribute hat ein ungültiges Format.',
    'required' => 'Bitte füllen Sie das Feld „:attribute“ aus.',
    'required_if' => 'Bitte füllen Sie das Feld „:attribute“ aus.',
    'required_with' => 'Bitte füllen Sie das Feld „:attribute“ aus.',
    'size' => [
        'array' => ':Attribute muss genau :size Einträge haben.',
        'file' => ':Attribute muss genau :size Kilobyte groß sein.',
        'numeric' => ':Attribute muss genau :size sein.',
        'string' => ':Attribute muss genau :size Zeichen lang sein.',
    ],
    'string' => ':Attribute muss ein Text sein.',
    'unique' => ':Attribute ist bereits vergeben.',

    /*
    | Feldnamen, die in mehreren Formularen vorkommen (Form Requests können sie überschreiben).
    */
    'attributes' => [
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'current_password' => 'aktuelles Passwort',
        'username' => 'Benutzerkennung',
        'name' => 'Name',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'phone' => 'Telefon',
        'pin' => 'PIN',
        'body' => 'Nachricht',
        'confirm' => 'die Bestätigung',
    ],

];
