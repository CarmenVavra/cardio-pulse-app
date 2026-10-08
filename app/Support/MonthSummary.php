<?php

namespace App\Support;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use App\Models\MonthlyReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monatsübersicht eines Patienten: Kalender (Farbe = höchster Wert des Tages) und Kennzahlen.
 */
final class MonthSummary
{
    /** @var array<int, BloodPressureStatus|null> Tag (1–31) → dringlichster Status */
    public readonly array $days;

    public readonly MeasurementStats $stats;

    /**
     * @param  Collection<int, Measurement>  $measurements
     */
    public function __construct(
        public readonly Carbon $month,
        public readonly Collection $measurements,
        public readonly ?MonthlyReport $report,
    ) {
        $days = [];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $days[$day] = null;
        }

        foreach ($measurements as $measurement) {
            $day = $measurement->measured_at->day;
            $days[$day] = BloodPressureStatus::worst($days[$day], $measurement->status);
        }

        $this->days = $days;
        $this->stats = MeasurementStats::from($measurements);
    }

    /**
     * Anzahl leerer Kalenderzellen vor dem 1. (Woche beginnt Montag).
     */
    public function leadingBlanks(): int
    {
        return $this->month->dayOfWeekIso - 1;
    }

    public function trailingBlanks(): int
    {
        $cells = $this->leadingBlanks() + count($this->days);

        return (7 - $cells % 7) % 7;
    }

    public function daysWithData(): int
    {
        return count(array_filter($this->days));
    }

    /**
     * Versand ist ab dem letzten Tag des Monats möglich.
     */
    public function sendableFrom(): Carbon
    {
        return $this->month->copy()->endOfMonth()->startOfDay();
    }

    public function canSend(): bool
    {
        return $this->stats->count > 0 && now()->greaterThanOrEqualTo($this->sendableFrom());
    }

    public function isCurrentMonth(): bool
    {
        return $this->month->isSameMonth(now());
    }
}
