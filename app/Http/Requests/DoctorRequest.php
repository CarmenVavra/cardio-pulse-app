<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Arzt anlegen/bearbeiten (nur Krankenhaus-Personal).
 */
class DoctorRequest extends FormRequest
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
        $doctor = $this->route('doctor');
        $id = $doctor instanceof User ? $doctor->id : null;
        $creating = $id === null;

        return [
            'title' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\/\s-]{5,}$/'],
            'available_until' => ['nullable', 'date_format:H:i'],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'pin' => [$creating ? 'required' : 'nullable', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'name' => 'Vor- und Nachname',
            'username' => 'Benutzerkennung',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'available_until' => 'Erreichbar bis',
            'password' => 'Passwort',
            'pin' => 'PIN',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Die Benutzerkennung darf nur Kleinbuchstaben, Ziffern, Punkt, Binde- und Unterstrich enthalten (z. B. m.weber).',
            'username.unique' => 'Diese Benutzerkennung ist bereits vergeben (auch gelöschte Konten bleiben reserviert).',
            'email.unique' => 'Diese E-Mail ist bereits vergeben (auch gelöschte Konten bleiben reserviert).',
            'phone.regex' => 'Bitte eine gültige Telefonnummer eingeben.',
            'pin.digits' => 'Die PIN für den Privacy-Lock muss aus genau 6 Ziffern bestehen.',
        ];
    }

    /**
     * Profildaten ohne Zugangsdaten.
     *
     * @return array{title: ?string, name: string, username: string, email: string, phone: ?string, available_until: ?string}
     */
    public function profile(): array
    {
        return [
            'title' => $this->validated('title'),
            'name' => $this->validated('name'),
            'username' => mb_strtolower($this->validated('username')),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'available_until' => $this->validated('available_until'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => mb_strtolower(trim((string) $this->input('username')))]);
    }
}
