<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Deutsche Kurzformate für Zeiten in Listen und Kopfzeilen.
 */
class Format
{
    /**
     * Relative Upload-Zeit für das Live-Board, z. B. "vor 2 Min", "vor 3 Std", "Gestern 19:30".
     */
    public static function ago(Carbon $time, ?Carbon $now = null): string
    {
        $now ??= now();
        $minutes = (int) floor($time->diffInMinutes($now, true));

        if ($minutes < 1) {
            return 'gerade eben';
        }
        if ($minutes < 60) {
            return 'vor '.$minutes.' Min';
        }
        if ($time->isSameDay($now)) {
            return 'vor '.intdiv($minutes, 60).' Std';
        }

        return self::day($time, $now);
    }

    /**
     * "Heute 08:40", "Gestern 19:30" oder "07.10. 19:30".
     */
    public static function day(Carbon $time, ?Carbon $now = null): string
    {
        $now ??= now();

        if ($time->isSameDay($now)) {
            return 'Heute '.$time->format('H:i');
        }
        if ($time->isSameDay($now->copy()->subDay())) {
            return 'Gestern '.$time->format('H:i');
        }

        return $time->format('d.m. H:i');
    }

    public static function monthName(Carbon $month): string
    {
        return $month->locale('de')->translatedFormat('F Y');
    }

    public static function duration(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
