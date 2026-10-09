<?php

namespace App\Support;

use App\Models\Measurement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Geometrie für den 30-Tage-Verlauf (alle Messungen, mehrere pro Tag).
 * Wertebereich der y-Achse: 60–210 mmHg.
 */
class TrendChart
{
    public readonly float $y180;

    public readonly float $y130;

    public readonly float $y90;

    public readonly string $systolicPath;

    public readonly string $diastolicPath;

    /** @var list<array{x: float, y: float, status: string, label: string}> */
    public readonly array $dots;

    /** @var list<string> */
    public readonly array $ticks;

    /**
     * @param  Collection<int, Measurement>  $measurements
     */
    public function __construct(
        Collection $measurements,
        Carbon $from,
        Carbon $to,
        public readonly int $width = 1000,
        public readonly int $height = 250,
    ) {
        $span = max(1, $from->diffInSeconds($to, true));
        $x = fn (Carbon $t) => round(($t->getTimestamp() - $from->getTimestamp()) / $span * $width, 1);

        $this->y180 = $this->y(180);
        $this->y130 = $this->y(130);
        $this->y90 = $this->y(90);

        $sorted = $measurements->sortBy(fn (Measurement $m) => $m->measured_at->getTimestamp())->values();

        $line = fn (string $field) => $sorted
            ->map(fn (Measurement $m, int $i) => ($i === 0 ? 'M' : 'L').$x($m->measured_at).' '.$this->y($m->{$field}))
            ->implode(' ');

        $this->systolicPath = $line('systolic');
        $this->diastolicPath = $line('diastolic');

        $this->dots = $sorted->map(fn (Measurement $m) => [
            'x' => $x($m->measured_at),
            'y' => $this->y($m->systolic),
            'status' => $m->status->value,
            'label' => $m->measured_at->format('d.m. H:i').' · '.$m->reading().' mmHg · '.$m->category()->label(),
        ])->all();

        $ticks = [];
        for ($i = 0; $i <= 4; $i++) {
            $tick = $from->copy()->addSeconds((int) round($span / 4 * $i));
            $ticks[] = $tick->isToday() ? 'Heute' : $tick->format('d.m.');
        }
        $this->ticks = $ticks;
    }

    public function y(int|float $value): float
    {
        $value = max(55, min(215, $value));

        return round((210 - $value) / 150 * $this->height, 1);
    }

    public function isEmpty(): bool
    {
        return $this->dots === [];
    }
}
