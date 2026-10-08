<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MonthlyReport;
use App\Models\Patient;
use App\Support\MonthSummary;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Monatsbericht: bündelt die Observations eines Monats und überträgt sie an das Krankenhaus.
 */
class MonthlyReportService
{
    public function summary(Patient $patient, Carbon $month): MonthSummary
    {
        $month = $month->copy()->startOfMonth();

        $measurements = $patient->measurements()
            ->whereBetween('measured_at', [$month, $month->copy()->endOfMonth()])
            ->orderBy('measured_at')
            ->get();

        $report = $patient->monthlyReports()
            ->whereDate('month', $month->toDateString())
            ->first();

        return new MonthSummary($month, $measurements, $report);
    }

    public function send(Patient $patient, Carbon $month): MonthlyReport
    {
        $summary = $this->summary($patient, $month);

        if (! $summary->canSend()) {
            throw ValidationException::withMessages([
                'month' => 'Der Monatsbericht kann ab dem '.$summary->sendableFrom()->format('d.m.').' gesendet werden.',
            ]);
        }

        // Vorhandenen Bericht über den datumsgenauen Abgleich der Monatsübersicht wiederverwenden:
        // "month" ist als Datum mit Uhrzeit gespeichert, ein exakter Stringvergleich findet ihn nicht.
        $resent = $summary->report !== null;
        $report = $summary->report ?? new MonthlyReport([
            'patient_id' => $patient->id,
            'month' => $summary->month,
        ]);

        $report->fill([
            'sent_at' => now(),
            'measurement_count' => $summary->stats->count,
            'avg_systolic' => $summary->stats->avgSystolic,
            'avg_diastolic' => $summary->stats->avgDiastolic,
            'worst_status' => $summary->stats->worst,
        ])->save();

        AuditLog::record($resent ? 'monthly_report.resent' : 'monthly_report.sent', $report, [
            'month' => $summary->month->format('Y-m'),
            'measurements' => $summary->stats->count,
        ]);

        return $report;
    }
}
