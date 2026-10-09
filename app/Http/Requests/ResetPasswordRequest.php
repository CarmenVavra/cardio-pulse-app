<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Neues Passwort über den Link aus der E-Mail festlegen.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:200'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'E-Mail',
            'password' => 'Neues Passwort',
        ];
    }

    /**
     * @return array{email: string, password: string, password_confirmation: string, token: string}
     */
    public function credentials(): array
    {
        return [
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
            'token' => (string) $this->validated('token'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => trim((string) $this->input('email'))]);
    }
}
