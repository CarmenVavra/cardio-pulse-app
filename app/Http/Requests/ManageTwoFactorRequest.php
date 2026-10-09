<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Zwei-Faktor-Anmeldung abschalten oder neue Wiederherstellungscodes erzeugen – nur mit Passwort.
 */
class ManageTwoFactorRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'twoFactorManage';

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
            'two_factor_password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->user()?->hasTwoFactor()) {
                    $validator->errors()->add('two_factor_password', 'Die Zwei-Faktor-Anmeldung ist nicht eingerichtet.');
                }

                if ($this->routeIs('account.two-factor.destroy') && config('cardiopulse.require_two_factor')) {
                    $validator->errors()->add('two_factor_password', 'Die Zwei-Faktor-Anmeldung ist vorgeschrieben und kann nicht abgeschaltet werden. Für ein neues Handy richten Sie sie einfach neu ein.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['two_factor_password' => 'Passwort'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['two_factor_password.current_password' => 'Das Passwort ist nicht korrekt.'];
    }
}
