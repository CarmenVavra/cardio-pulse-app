<?php

namespace App\Support;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use Illuminate\Support\Collection;

/**
 * Kennzahlen über eine Menge von Messungen (Ø, Anzahl, Ampel-Verteilung).
 */
final class MeasurementStats
{
    /**
     * @param  array<string, int>  $distribution
     */
    public function __construct(
        public readonly int $count,
        public readonly ?int $avgSystolic,
        public readonly ?int $avgDiastolic,
        public readonly array $distribution,
        public readonly ?BloodPressureStatus $worst,
    ) {}

    /**
     * @param  Collection<int, Measurement>  $measurements
     */
    public static function from(Collection $measurements): self
    {
        $distribution = array_fill_keys(array_map(fn (BloodPressureStatus $s) => $s->value, BloodPressureStatus::cases()), 0);
        $worst = null;

        foreach ($measurements as $measurement) {
            $distribution[$measurement->status->value]++;
            $worst = BloodPressureStatus::worst($worst, $measurement->status);
        }

        $count = $measurements->count();

        return new self(
            count: $count,
            avgSystolic: $count > 0 ? (int) round((float) $measurements->avg('systolic')) : null,
            avgDiastolic: $count > 0 ? (int) round((float) $measurements->avg('diastolic')) : null,
            distribution: $distribution,
            worst: $worst,
        );
    }

    public function average(): string
    {
        return $this->count > 0 ? $this->avgSystolic.'/'.$this->avgDiastolic : '—';
    }

    public function averageStatus(): ?BloodPressureStatus
    {
        if ($this->avgSystolic === null || $this->avgDiastolic === null) {
            return null;
        }

        return BloodPressureStatus::classify($this->avgSystolic, $this->avgDiastolic);
    }

    public function greenPercent(): int
    {
        return $this->count > 0 ? (int) round($this->distribution['green'] / $this->count * 100) : 0;
    }

    public function distributionLabel(): string
    {
        return $this->distribution['red'].' rot · '
            .$this->distribution['amber'].' gelb-orange · '
            .$this->distribution['green'].' grün · '
            .$this->distribution['white'].' weiß';
    }
}
