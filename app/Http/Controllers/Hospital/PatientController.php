<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\MonitoringBoard;
use App\Services\PatientOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * D4 – Patientendetail mit 30-Tage-Verlauf.
 */
class PatientController extends Controller
{
    public function index(MonitoringBoard $board): RedirectResponse
    {
        $first = $board->rows()->first();

        abort_if($first === null, 404, 'Keine Patienten vorhanden.');

        return redirect()->route('patients.show', $first->patient);
    }

    public function show(Patient $patient, MonitoringBoard $board, PatientOverviewService $overviews): View
    {
        $rows = $board->rows();
        $overview = $overviews->build($patient, now()->subDays(30)->startOfDay(), now());

        return view('hospital.patients.show', compact('rows', 'overview'));
    }
}
