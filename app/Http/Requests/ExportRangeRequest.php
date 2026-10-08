<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Zeitraum für FHIR-/PDF-Export: ?month=YYYY-MM, sonst die letzten 30 Tage.
 */
class ExportRangeRequest extends FormRequest
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
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $month = $this->validated('month');

        if ($month) {
            $start = Carbon::createFromFormat('!Y-m', $month);

            return [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()];
        }

        return [now()->subDays(30)->startOfDay(), now()];
    }
}
