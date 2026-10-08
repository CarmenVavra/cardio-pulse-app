<?php

namespace App\Support;

use App\Models\Measurement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 7-Tage-Sparkline (100 × 28) aus den systolischen Tagesmittelwerten.
 */
class Sparkline
{
    public const WIDTH = 100;

    public const HEIGHT = 28;

    /**
     * @param  Collection<int, Measurement>  $measurements
     */
    public static function path(Collection $measurements, ?Carbon $today = null): ?string
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $points = [];

        for ($day = 0; $day < 7; $day++) {
            $date = $today->copy()->subDays(6 - $day);
            $values = $measurements
                ->filter(fn (Measurement $m) => $m->measured_at->isSameDay($date))
                ->pluck('systolic');

            if ($values->isEmpty()) {
                continue;
            }

            $x = $day * 16 + 2;
            $y = max(2, min(26, 26 - ((float) $values->avg() - 70) / 140 * 24));
            $points[] = [$x, round($y, 1)];
        }

        if ($points === []) {
            return null;
        }
        if (count($points) === 1) {
            $points[] = [$points[0][0] + 0.1, $points[0][1]];
        }

        return collect($points)
            ->map(fn (array $p, int $i) => ($i === 0 ? 'M' : 'L').$p[0].' '.$p[1])
            ->implode(' ');
    }
}
