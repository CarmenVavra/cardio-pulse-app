<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Termin absagen – Krankenhaus jederzeit, Patient nur eigene Termine vor Beginn.
 */
class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');
        $user = $this->user();

        if (! $appointment instanceof Appointment || $user === null || $appointment->status !== AppointmentStatus::Booked) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        return $user->patient?->id === $appointment->patient_id && $appointment->canBeCancelledByPatient();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
