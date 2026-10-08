<?php

namespace App\Http\Requests;

use App\Models\Medication;
use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Medikation erfassen/bearbeiten – gemeinsam für Arzt (Krankenhaus) und Patient (App).
 */
class MedicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $medication = $this->route('medication');

        if ($user === null) {
            return false;
        }

        if ($medication instanceof Medication) {
            return $user->can('update', $medication);
        }

        $patient = $this->route('patient') ?? $user->patient;

        return $patient instanceof Patient && $user->can('create', [Medication::class, $patient]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $amount = ['required', 'string', Rule::in(Medication::AMOUNTS)];

        return [
            'name' => ['required', 'string', 'max:100'],
            'dose' => ['required', 'string', 'max:40'],
            'morning' => $amount,
            'noon' => $amount,
            'evening' => $amount,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Medikament',
            'dose' => 'Dosis',
            'morning' => 'morgens',
            'noon' => 'mittags',
            'evening' => 'abends',
        ];
    }

    /**
     * @return array{name: string, dose: string, schedule: string}
     */
    public function medication(): array
    {
        return [
            'name' => trim($this->validated('name')),
            'dose' => trim($this->validated('dose')),
            'schedule' => Medication::composeSchedule(
                $this->validated('morning'),
                $this->validated('noon'),
                $this->validated('evening'),
            ),
        ];
    }
}
