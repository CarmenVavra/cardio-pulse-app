<?php

namespace App\Enums;

/**
 * Antwort des Patienten nach der Notfalltaste: Wer ruft die Rettung?
 */
enum SosResponse: string
{
    case SelfCalling = 'self';
    case HospitalCalls = 'hospital';

    public function label(): string
    {
        return match ($this) {
            self::SelfCalling => 'Ruft selbst den Notruf an',
            self::HospitalCalls => 'Krankenhaus soll die Rettung rufen',
        };
    }
}
