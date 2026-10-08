<?php

namespace App\Enums;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Active = 'active';
    case Ended = 'ended';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Ringing => 'Klingelt',
            self::Active => 'Laufendes Gespräch',
            self::Ended => 'Beendet',
            self::Declined => 'Abgelehnt',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Ringing || $this === self::Active;
    }
}
