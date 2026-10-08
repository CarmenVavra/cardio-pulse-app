<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ReportIndexRequest extends FormRequest
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
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'patient' => ['nullable', 'integer', 'exists:patients,id'],
        ];
    }

    public function month(): ?Carbon
    {
        $month = $this->validated('month');

        return $month ? Carbon::createFromFormat('!Y-m', $month) : null;
    }
}
