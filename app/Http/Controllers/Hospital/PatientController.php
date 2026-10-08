<?php

namespace App\Http\Controllers\Hospital;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePatientRequest;
use App\Http\Requests\PatientRequest;
use App\Models\Patient;
use App\Models\User;
use App\Services\MonitoringBoard;
use App\Services\PatientOverviewService;
use App\Services\PatientService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * D4 – Patientendetail mit 30-Tage-Verlauf; Patienten anlegen und bearbeiten.
 */
class PatientController extends Controller
{
    public function index(Request $request, MonitoringBoard $board): RedirectResponse
    {
        // Statusmeldung (z. B. nach dem Löschen) über die Weiterleitung hinweg erhalten.
        $request->session()->reflash();

        $first = $board->rows()->first();

        if ($first === null) {
            return redirect()->route('patients.create');
        }

        return redirect()->route('patients.show', $first->patient);
    }

    public function show(Patient $patient, MonitoringBoard $board, PatientOverviewService $overviews): View
    {
        $rows = $board->rows();
        $overview = $overviews->build($patient, now()->subDays(30)->startOfDay(), now());

        return view('hospital.patients.show', compact('rows', 'overview'));
    }

    public function create(Request $request, PatientService $patients): View
    {
        $patient = new Patient(['doctor_id' => $request->user()->id]);
        $doctors = $this->doctors();
        $nextNumber = $patients->nextPatientNumber();

        return view('hospital.patients.form', compact('patient', 'doctors', 'nextNumber'));
    }

    public function store(PatientRequest $request, PatientService $patients): RedirectResponse
    {
        $patient = $patients->create(
            $request->patientData(),
            $request->validated('email'),
            $request->validated('password'),
            $request->user(),
        );

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', $patient->fullName().' wurde angelegt ('.$patient->patient_number.').');
    }

    public function edit(Patient $patient): View
    {
        $patient->load('user');
        $doctors = $this->doctors();
        $nextNumber = null;

        return view('hospital.patients.form', compact('patient', 'doctors', 'nextNumber'));
    }

    public function update(PatientRequest $request, Patient $patient, PatientService $patients): RedirectResponse
    {
        $patients->update(
            $patient,
            $request->patientData(),
            $request->validated('email'),
            $request->validated('password'),
            $request->user(),
        );

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', 'Patientendaten gespeichert.');
    }

    public function destroy(DeletePatientRequest $request, Patient $patient, PatientService $patients): RedirectResponse
    {
        $patients->delete($patient, $request->user(), $request->validated('reason'));

        return redirect()
            ->route('patients.index')
            ->with('status', $patient->fullName().' ('.$patient->patient_number.') wurde gelöscht. Die Behandlungsdaten bleiben archiviert.');
    }

    /**
     * @return Collection<int, User>
     */
    private function doctors(): Collection
    {
        return User::query()->where('role', UserRole::Staff)->orderBy('name')->get();
    }
}
