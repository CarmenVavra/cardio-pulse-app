<?php

namespace App\Services;

/**
 * Ergebnis einer PIN-Prüfung am Privacy-Lock.
 */
enum PinCheck
{
    case Valid;
    case Invalid;
    case LockedOut;
}
