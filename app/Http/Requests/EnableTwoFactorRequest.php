<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Zwei-Faktor-Anmeldung einrichten: aktuelles Passwort und erster Code aus der App.
 */
class EnableTwoFactorRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'twoFactor';

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
            'current_password' => ['required', 'string', 'current_password'],
            'code' => ['required', 'string', 'regex:/^\s*\d{3}\s?\d{3}\s*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'Passwort',
            'code' => 'Code',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Das Passwort ist nicht korrekt.',
            'code.regex' => 'Bitte die 6 Ziffern aus der App eingeben.',
        ];
    }
}
