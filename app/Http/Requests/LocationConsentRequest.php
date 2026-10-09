<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Einwilligung des Patienten, im Notfall seinen Standort zu übermitteln (widerrufbar).
 */
class LocationConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->patient !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consent' => ['required', 'boolean'],
            'from' => ['nullable', 'in:sos,account'],
        ];
    }

    public function fromSosPage(): bool
    {
        return $this->validated('from') === 'sos';
    }

    public function consent(): bool
    {
        return (bool) $this->validated('consent');
    }
}
