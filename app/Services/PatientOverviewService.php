<?php

namespace App\Services;

use App\Models\Patient;
use App\Support\MeasurementStats;
use App\Support\PatientOverview;
use App\Support\TrendChart;
use Illuminate\Support\Carbon;

/**
 * Detailansicht (Patient / Monatsbericht) mit 30-Tage-Verlauf, Kennzahlen und Medikation.
 */
class PatientOverviewService
{
    public function build(Patient $patient, Carbon $from, Carbon $to): PatientOverview
    {
        $patient->loadMissing([
            'medications',
            'latestMeasurement',
            'latestAlarm.acknowledgedBy',
            'latestMonthlyReport',
        ]);

        $measurements = $patient->measurements()
            ->whereBetween('measured_at', [$from, $to])
            ->orderBy('measured_at')
            ->get();

        $recent = $patient->measurements()
            ->latest('measured_at')
            ->limit(4)
            ->get();

        return new PatientOverview(
            patient: $patient,
            from: $from,
            to: $to,
            stats: MeasurementStats::from($measurements),
            chart: new TrendChart($measurements, $from, $to),
            recentUploads: $recent,
        );
    }
}
