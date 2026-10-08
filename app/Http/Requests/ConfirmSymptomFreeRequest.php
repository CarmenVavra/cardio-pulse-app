<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * M4: "Ich habe keine Beschwerden" – erst nach Bestätigung der Symptomfreiheit.
 */
class ConfirmSymptomFreeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $measurement = $this->route('measurement');

        return $measurement !== null && (bool) $this->user()?->can('update', $measurement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symptom_free' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'symptom_free.accepted' => 'Bitte bestätigen Sie: kein Brustschmerz, keine Atemnot.',
        ];
    }
}
