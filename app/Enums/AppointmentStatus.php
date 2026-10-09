<?php

namespace App\Enums;

/**
 * Status eines Termins – Werte wie FHIR R4 Appointment.status.
 */
enum AppointmentStatus: string
{
    case Booked = 'booked';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Geplant',
            self::Fulfilled => 'Durchgeführt',
            self::Cancelled => 'Abgesagt',
        };
    }
}
