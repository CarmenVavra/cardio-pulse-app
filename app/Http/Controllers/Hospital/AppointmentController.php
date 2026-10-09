<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\StartAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;

/**
 * Termine für Videosprechstunden vereinbaren, absagen und starten.
 */
class AppointmentController extends Controller
{
    public function store(StoreAppointmentRequest $request, Patient $patient, AppointmentService $appointments): RedirectResponse
    {
        $appointment = $appointments->schedule(
            $patient,
            $request->doctor(),
            $request->startsAt(),
            $request->minutes(),
            $request->validated('reason'),
            $request->user(),
        );

        return redirect()
            ->to(route('patients.show', $patient).'#termine')
            ->with('status', 'Videosprechstunde vereinbart: '.$appointment->when().'. '.$patient->fullName().' wurde per E-Mail informiert.');
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $appointments->cancel($appointment, $request->user());

        return back()->with('status', 'Termin abgesagt: '.$appointment->when().'.');
    }

    public function start(StartAppointmentRequest $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $call = $appointments->start($appointment, $request->user());

        return redirect()->route('calls.show', $call);
    }
}
