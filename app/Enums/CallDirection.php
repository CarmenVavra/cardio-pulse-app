<?php

namespace App\Enums;

enum CallDirection: string
{
    /** Arzt ruft Patient an. */
    case ToPatient = 'to_patient';

    /** Patient ruft Arzt bzw. Telemedizin-Zentrale an. */
    case ToClinic = 'to_clinic';
}
