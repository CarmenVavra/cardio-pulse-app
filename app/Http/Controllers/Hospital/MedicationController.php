<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicationRequest;
use App\Models\Medication;
use App\Models\Patient;
use App\Services\MedicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Medikation eines Patienten durch den Arzt pflegen (D4).
 */
class MedicationController extends Controller
{
    public function store(MedicationRequest $request, Patient $patient, MedicationService $medications): RedirectResponse
    {
        $medication = $medications->create($patient, $request->medication(), $request->user());

        return back()->with('status', $medication->name.' wurde zur Medikation hinzugefügt.');
    }

    public function update(MedicationRequest $request, Patient $patient, Medication $medication, MedicationService $medications): RedirectResponse
    {
        $medications->update($medication, $request->medication(), $request->user());

        return back()->with('status', $medication->name.' wurde geändert.');
    }

    public function destroy(Request $request, Patient $patient, Medication $medication, MedicationService $medications): RedirectResponse
    {
        Gate::authorize('delete', $medication);

        $medications->delete($medication, $request->user());

        return back()->with('status', $medication->name.' wurde aus der Medikation entfernt.');
    }
}
