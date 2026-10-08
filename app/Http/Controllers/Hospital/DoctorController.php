<?php

namespace App\Http\Controllers\Hospital;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteDoctorRequest;
use App\Http\Requests\DoctorRequest;
use App\Models\User;
use App\Services\DoctorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Ärzteverwaltung: anlegen, bearbeiten, löschen.
 */
class DoctorController extends Controller
{
    public function index(): View
    {
        $doctors = User::query()
            ->where('role', UserRole::Staff)
            ->withCount('patients')
            ->orderBy('name')
            ->get();

        return view('hospital.doctors.index', compact('doctors'));
    }

    public function create(): View
    {
        $doctor = new User(['title' => 'Dr.', 'available_until' => '16:00']);
        $replacements = collect();

        return view('hospital.doctors.form', compact('doctor', 'replacements'));
    }

    public function store(DoctorRequest $request, DoctorService $doctors): RedirectResponse
    {
        $doctor = $doctors->create(
            $request->profile(),
            $request->validated('password'),
            $request->validated('pin'),
            $request->user(),
        );

        return redirect()
            ->route('doctors.index')
            ->with('status', $doctor->displayName().' wurde angelegt.');
    }

    public function edit(User $doctor): View
    {
        $doctor->loadCount('patients');
        $replacements = User::query()
            ->where('role', UserRole::Staff)
            ->whereKeyNot($doctor->id)
            ->orderBy('name')
            ->get();

        return view('hospital.doctors.form', compact('doctor', 'replacements'));
    }

    public function update(DoctorRequest $request, User $doctor, DoctorService $doctors): RedirectResponse
    {
        $doctors->update(
            $doctor,
            $request->profile(),
            $request->validated('password'),
            $request->validated('pin'),
            $request->user(),
        );

        return redirect()
            ->route('doctors.index')
            ->with('status', $doctor->displayName().' wurde gespeichert.');
    }

    public function destroy(DeleteDoctorRequest $request, User $doctor, DoctorService $doctors): RedirectResponse
    {
        $replacement = $request->replacement();
        $patients = $doctor->patients()->count();

        $doctors->delete($doctor, $request->user(), $replacement);

        $message = $doctor->displayName().' wurde gelöscht.';
        if ($replacement !== null && $patients > 0) {
            $message .= ' '.$patients.' '.($patients === 1 ? 'Patient wurde' : 'Patienten wurden').' '.$replacement->displayName().' zugewiesen.';
        }

        return redirect()->route('doctors.index')->with('status', $message);
    }
}
