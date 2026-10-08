<?php

namespace App\Http\Controllers\Patient;

use App\Services\MonthlyReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * M1 – Start: letzte Messung, Messungen von heute, Fortschritt des Monatsberichts.
 */
class HomeController extends PatientAreaController
{
    public function index(Request $request, MonthlyReportService $reports): View
    {
        $patient = $this->patient($request)->load(['latestMeasurement', 'medications', 'doctor']);

        $today = $patient->measurements()
            ->where('measured_at', '>=', today())
            ->latest('measured_at')
            ->get();

        $month = $reports->summary($patient, now());
        $unreadMessages = $patient->messages()->fromClinic()->unread()->count();

        return view('patient.home', compact('patient', 'today', 'month', 'unreadMessages'));
    }
}
