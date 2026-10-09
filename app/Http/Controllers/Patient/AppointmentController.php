<?php

namespace App\Http\Controllers\Patient;

use App\Http\Requests\CancelAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;

/**
 * Patient sagt einen Termin für die Videosprechstunde ab.
 */
class AppointmentController extends PatientAreaController
{
    public function cancel(CancelAppointmentRequest $request, Appointment $appointment, AppointmentService $appointments): RedirectResponse
    {
        $appointments->cancel($appointment, $request->user());

        return redirect()
            ->route('patient.doctor')
            ->with('status', 'Ihr Termin am '.$appointment->when().' ist abgesagt. Ihr Behandlungsteam wurde benachrichtigt.');
    }
}
