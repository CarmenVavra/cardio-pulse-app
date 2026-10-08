<?php

namespace App\Services;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use App\Models\MonthlyReport;
use App\Models\Patient;
use App\Support\BoardRow;
use App\Support\Sparkline;
use Illuminate\Support\Collection;

/**
 * Daten für den 24/7-Überwachungsscreen: Triage-Sortierung Rot → Gelb-Orange → Grün → Weiß.
 */
class MonitoringBoard
{
    /**
     * @return Collection<int, BoardRow>
     */
    public function rows(): Collection
    {
        $since = now()->subDays(6)->startOfDay();

        $patients = Patient::query()
            ->with([
                'latestMeasurement',
                'latestMonthlyReport',
                'measurements' => fn ($query) => $query->where('measured_at', '>=', $since),
                'alarms' => fn ($query) => $query->open(),
            ])
            ->get();

        return $patients
            ->map(fn (Patient $patient) => $this->row($patient))
            ->sortBy(fn (BoardRow $row) => $row->sortKey())
            ->values();
    }

    /**
     * @param  Collection<int, BoardRow>  $rows
     * @return array<string, int>
     */
    public function counts(Collection $rows): array
    {
        $counts = array_fill_keys(array_map(fn (BloodPressureStatus $s) => $s->value, BloodPressureStatus::cases()), 0);

        foreach ($rows as $row) {
            if ($row->status !== null) {
                $counts[$row->status->value]++;
            }
        }

        return $counts;
    }

    public function uploadsToday(): int
    {
        return Measurement::query()->whereHas('patient')->where('measured_at', '>=', today())->count()
            + MonthlyReport::query()->whereHas('patient')->where('sent_at', '>=', today())->count();
    }

    private function row(Patient $patient): BoardRow
    {
        $measurement = $patient->latestMeasurement;
        $report = $patient->latestMonthlyReport;

        $uploadedAt = $measurement?->measured_at;
        $kind = 'Einzelmessung';

        if ($report !== null && ($uploadedAt === null || $report->sent_at->greaterThan($uploadedAt))) {
            $uploadedAt = $report->sent_at;
            $kind = 'Monatsbericht';
        }

        return new BoardRow(
            patient: $patient,
            measurement: $measurement,
            status: $measurement?->status,
            uploadedAt: $uploadedAt,
            uploadKind: $kind,
            sparkline: Sparkline::path($patient->measurements),
            hasOpenAlarm: $patient->alarms->isNotEmpty(),
        );
    }
}
