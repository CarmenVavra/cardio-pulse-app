<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Patient anlegen/bearbeiten (nur Krankenhaus-Personal).
 */
class PatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $patient = $this->route('patient');
        $userId = $patient instanceof Patient ? $patient->user_id : null;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'street' => ['required', 'string', 'max:150'],
            'postal_code' => ['required', 'string', 'regex:/^\d{4,5}$/'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\/\s-]{5,}$/'],
            'diagnosis' => ['nullable', 'string', 'max:150'],
            'gp_name' => ['nullable', 'string', 'max:100'],
            'doctor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Staff->value)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'birth_date' => 'Geburtsdatum',
            'street' => 'Straße und Hausnummer',
            'postal_code' => 'PLZ',
            'city' => 'Ort',
            'phone' => 'Telefon',
            'diagnosis' => 'Diagnose',
            'gp_name' => 'Hausarzt',
            'doctor_id' => 'Behandelnder Arzt',
            'email' => 'E-Mail (App-Zugang)',
            'password' => 'Passwort',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Die PLZ muss aus 4 oder 5 Ziffern bestehen.',
            'phone.regex' => 'Bitte eine gültige Telefonnummer eingeben.',
        ];
    }

    /**
     * Stammdaten für das Patient-Model.
     *
     * @return array<string, mixed>
     */
    public function patientData(): array
    {
        return $this->safe()->except(['email', 'password', 'password_confirmation']);
    }
}
