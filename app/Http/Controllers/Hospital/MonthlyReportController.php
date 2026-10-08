<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIndexRequest;
use App\Models\MonthlyReport;
use App\Services\PatientOverviewService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * D4 – Eingegangene Monatsberichte mit Verlauf des Berichtsmonats.
 */
class MonthlyReportController extends Controller
{
    public function index(ReportIndexRequest $request, PatientOverviewService $overviews): View
    {
        $latestMonth = MonthlyReport::query()->max('month');
        $month = ($request->month() ?? ($latestMonth ? Carbon::parse($latestMonth) : now()->subMonth()))->startOfMonth();

        $reports = MonthlyReport::query()
            ->whereDate('month', $month->toDateString())
            ->with('patient')
            ->get()
            ->sortBy(fn (MonthlyReport $report) => ($report->worst_status?->sortOrder() ?? 9).'-'.$report->patient->last_name)
            ->values();

        $selected = $reports->firstWhere('patient_id', (int) $request->validated('patient')) ?? $reports->first();

        $overview = $selected
            ? $overviews->build($selected->patient, $month->copy(), $month->copy()->endOfMonth())
            : null;

        $previousMonth = $month->copy()->subMonth();
        $nextMonth = $month->copy()->addMonth();
        $hasNext = $nextMonth->lessThanOrEqualTo(now()->startOfMonth());

        return view('hospital.reports.index', compact('reports', 'selected', 'overview', 'month', 'previousMonth', 'nextMonth', 'hasNext'));
    }
}
