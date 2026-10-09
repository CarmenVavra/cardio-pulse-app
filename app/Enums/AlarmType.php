<?php

namespace App\Enums;

/**
 * Auslöser eines Alarms: gefährlich hoher Messwert oder die Notfalltaste des Patienten.
 */
enum AlarmType: string
{
    case Measurement = 'measurement';
    case Sos = 'sos';

    public function label(): string
    {
        return match ($this) {
            self::Measurement => 'Gefährlich hoher Wert',
            self::Sos => 'Notfalltaste',
        };
    }
}
