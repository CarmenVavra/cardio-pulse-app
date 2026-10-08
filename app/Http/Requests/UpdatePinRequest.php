<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Eigene Privacy-Lock-PIN ändern (nur Krankenhaus-Personal).
 */
class UpdatePinRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'pin';

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
        return [
            'pin_current_password' => ['required', 'string', 'current_password'],
            'pin' => ['required', 'digits:6', 'confirmed', 'not_in:000000,111111,123456,654321,123123'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pin_current_password' => 'Passwort',
            'pin' => 'neue PIN',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin_current_password.current_password' => 'Das Passwort ist nicht korrekt.',
            'pin.not_in' => 'Diese PIN ist zu leicht zu erraten.',
        ];
    }
}
