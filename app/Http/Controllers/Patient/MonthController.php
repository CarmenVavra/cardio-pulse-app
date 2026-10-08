<?php

namespace App\Http\Controllers\Patient;

use App\Services\MonthlyReportService;
use App\Support\TrendChart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * M5 – Monatsübersicht, Versand an das Krankenhaus, PDF für den Hausarzt.
 */
class MonthController extends PatientAreaController
{
    public function show(Request $request, MonthlyReportService $reports, ?string $month = null): View
    {
        $patient = $this->patient($request)->load('doctor');
        $summary = $reports->summary($patient, $this->month($month));

        $previousMonth = $summary->month->copy()->subMonth();
        $nextMonth = $summary->month->copy()->addMonth();
        $hasNext = $nextMonth->lessThanOrEqualTo(now()->startOfMonth());

        return view('patient.month', compact('patient', 'summary', 'previousMonth', 'nextMonth', 'hasNext'));
    }

    public function send(Request $request, MonthlyReportService $reports, string $month): RedirectResponse
    {
        $reports->send($this->patient($request), $this->month($month));

        return redirect()
            ->route('patient.month', $month)
            ->with('status', 'Monatsbericht an das Krankenhaus gesendet.');
    }

    public function print(Request $request, MonthlyReportService $reports, string $month): View
    {
        $patient = $this->patient($request)->load(['doctor', 'medications']);
        $summary = $reports->summary($patient, $this->month($month));
        $chart = new TrendChart($summary->measurements, $summary->month->copy(), $summary->month->copy()->endOfMonth());

        return view('patient.month-print', compact('patient', 'summary', 'chart'));
    }

    private function month(?string $month): Carbon
    {
        $date = $month ? Carbon::createFromFormat('!Y-m', $month) : now()->startOfMonth();

        abort_if($date === null || $date->greaterThan(now()), 404);

        return $date->startOfMonth();
    }
}
