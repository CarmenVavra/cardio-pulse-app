<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Eigenes Passwort ändern (Ärzte und Patienten).
 */
class UpdatePasswordRequest extends FormRequest
{
    /**
     * Fehler landen in einem eigenen Error-Bag, damit sie nicht beim PIN-Formular erscheinen.
     *
     * @var string
     */
    protected $errorBag = 'password';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'aktuelles Passwort',
            'password' => 'neues Passwort',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
            'password.different' => 'Das neue Passwort muss sich vom aktuellen unterscheiden.',
        ];
    }
}
