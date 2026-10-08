<?php

namespace App\Http\Controllers\Patient;

use App\Enums\BloodPressureStatus;
use App\Http\Requests\ConfirmSymptomFreeRequest;
use App\Http\Requests\StoreMeasurementRequest;
use App\Models\Measurement;
use App\Services\MeasurementRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * M2 – Blutdruck eintragen, M3 – Ergebnis, M4 – Ergebnis Rot (Sicherheits-Interruption).
 */
class MeasurementController extends PatientAreaController
{
    public function create(): View
    {
        $symptoms = config('cardiopulse.symptoms');
        $methods = config('cardiopulse.methods');

        return view('patient.measure', compact('symptoms', 'methods'));
    }

    public function store(StoreMeasurementRequest $request, MeasurementRecorder $recorder): RedirectResponse
    {
        $measurement = $recorder->record($this->patient($request), $request->validated());

        return redirect()->route('patient.measurements.show', $measurement);
    }

    public function show(Measurement $measurement): View
    {
        Gate::authorize('view', $measurement);

        if ($measurement->status === BloodPressureStatus::Red) {
            return view('patient.emergency', compact('measurement'));
        }

        return view('patient.result', compact('measurement'));
    }

    public function confirm(ConfirmSymptomFreeRequest $request, Measurement $measurement, MeasurementRecorder $recorder): RedirectResponse
    {
        $recorder->confirmSymptomFree($measurement);

        return redirect()->route('patient.home');
    }
}
