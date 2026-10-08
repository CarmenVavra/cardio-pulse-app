<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeasurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->patient !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'systolic' => ['required', 'integer', 'between:50,300', 'gt:diastolic'],
            'diastolic' => ['required', 'integer', 'between:30,200'],
            'pulse' => ['nullable', 'integer', 'between:30,250'],
            'method' => ['required', Rule::in(array_keys(config('cardiopulse.methods')))],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', Rule::in([...array_keys(config('cardiopulse.symptoms')), 'keine'])],
            'rested' => ['nullable', 'boolean'],
            'medication_taken' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'systolic' => 'oberer Wert',
            'diastolic' => 'unterer Wert',
            'pulse' => 'Puls',
            'method' => 'Erfassungsart',
            'symptoms' => 'Beschwerden',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'systolic.gt' => 'Der obere Wert muss größer als der untere Wert sein.',
        ];
    }
}
