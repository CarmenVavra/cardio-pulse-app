<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Videosprechstunde zum Termin starten (10 Min vorher bis 30 Min nach dem Ende).
 */
class StartAppointmentRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'appointment';

    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $appointment = $this->route('appointment');

                if (! $appointment instanceof Appointment || ! $appointment->canStart()) {
                    $validator->errors()->add('appointment', 'Die Videosprechstunde lässt sich ab '.Appointment::EARLY_START_MINUTES.' Minuten vor Beginn bis '.Appointment::LATE_START_MINUTES.' Minuten nach dem geplanten Ende starten.');
                }

                if ($appointment instanceof Appointment && $appointment->patient?->trashed()) {
                    $validator->errors()->add('appointment', 'Der Patient wurde gelöscht.');
                }
            },
        ];
    }
}
