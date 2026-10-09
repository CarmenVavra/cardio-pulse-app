<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Videosprechstunde für einen Patienten vereinbaren.
 */
class StoreAppointmentRequest extends FormRequest
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
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in(Appointment::DURATIONS)],
            'reason' => ['nullable', 'string', 'max:200'],
            'doctor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Staff->value)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = $this->startsAt();
                if ($start->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('time', 'Der Termin muss in der Zukunft liegen.');

                    return;
                }
                if ($start->greaterThan(now()->addYear())) {
                    $validator->errors()->add('date', 'Termine sind höchstens ein Jahr im Voraus möglich.');

                    return;
                }

                $doctor = $this->doctor();
                if (app(AppointmentService::class)->overlaps($doctor, $start, $this->endsAt())) {
                    $validator->errors()->add('time', $doctor->displayName().' hat zu dieser Zeit bereits einen Termin.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'Datum',
            'time' => 'Uhrzeit',
            'duration' => 'Dauer',
            'reason' => 'Anlass',
            'doctor_id' => 'Arzt',
        ];
    }

    public function startsAt(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $this->input('date').' '.$this->input('time'), config('app.timezone'))
            ?: now();
    }

    public function endsAt(): Carbon
    {
        return $this->startsAt()->addMinutes((int) $this->input('duration'));
    }

    public function minutes(): int
    {
        return (int) $this->validated('duration');
    }

    public function doctor(): User
    {
        return User::query()->findOrFail((int) $this->input('doctor_id'));
    }
}
