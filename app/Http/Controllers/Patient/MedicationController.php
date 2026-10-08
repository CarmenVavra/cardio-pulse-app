<?php

namespace App\Http\Controllers\Patient;

use App\Http\Requests\MedicationRequest;
use App\Models\Medication;
use App\Services\MedicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Patienten-App: eigene Medikation erfassen, bearbeiten, löschen.
 */
class MedicationController extends PatientAreaController
{
    public function index(Request $request): View
    {
        $patient = $this->patient($request)->load(['medications' => fn ($query) => $query->orderBy('name'), 'medications.updatedBy']);
        $amounts = Medication::AMOUNTS;

        return view('patient.medications', compact('patient', 'amounts'));
    }

    public function store(MedicationRequest $request, MedicationService $medications): RedirectResponse
    {
        $medication = $medications->create($this->patient($request), $request->medication(), $request->user());

        return redirect()
            ->route('patient.medications.index')
            ->with('status', $medication->name.' wurde hinzugefügt. Ihr Behandlungsteam sieht die Änderung.');
    }

    public function update(MedicationRequest $request, Medication $medication, MedicationService $medications): RedirectResponse
    {
        $medications->update($medication, $request->medication(), $request->user());

        return redirect()
            ->route('patient.medications.index')
            ->with('status', $medication->name.' wurde geändert. Ihr Behandlungsteam sieht die Änderung.');
    }

    public function destroy(Request $request, Medication $medication, MedicationService $medications): RedirectResponse
    {
        Gate::authorize('delete', $medication);

        $medications->delete($medication, $request->user());

        return redirect()
            ->route('patient.medications.index')
            ->with('status', $medication->name.' wurde entfernt. Ihr Behandlungsteam sieht die Änderung.');
    }
}
