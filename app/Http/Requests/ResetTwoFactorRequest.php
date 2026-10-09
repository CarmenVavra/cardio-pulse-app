<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Admin setzt die Zwei-Faktor-Anmeldung eines anderen Arztes zurück (z. B. Handy verloren).
 */
class ResetTwoFactorRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'twoFactorReset';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-doctors');
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
                $doctor = $this->route('doctor');

                if ($doctor instanceof User && $doctor->is($this->user())) {
                    $validator->errors()->add('two_factor', 'Ihre eigene Zwei-Faktor-Anmeldung verwalten Sie unter „Mein Konto“.');
                }
            },
        ];
    }
}
