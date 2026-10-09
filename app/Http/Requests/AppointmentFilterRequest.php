<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter der Terminliste unter „Anrufe“: nur eigene oder alle Ärzte, Zeitraum.
 * Ungültige Werte fallen auf die Voreinstellung zurück, statt einen Fehler zu zeigen.
 */
class AppointmentFilterRequest extends FormRequest
{
    public const DOCTORS = [
        'mine' => 'Nur meine Termine',
        'all' => 'Alle Ärzte',
    ];

    public const RANGES = [
        '14' => 'Nächste 14 Tage',
        '30' => 'Nächste 30 Tage',
        '90' => 'Nächste 3 Monate',
        'all' => 'Alle geplanten',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $doctor = $this->query('doctor');
        $range = $this->query('range');

        $this->merge([
            'doctor' => is_string($doctor) && array_key_exists($doctor, self::DOCTORS) ? $doctor : 'mine',
            'range' => is_string($range) && array_key_exists($range, self::RANGES) ? $range : '14',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doctor' => ['required', Rule::in(array_keys(self::DOCTORS))],
            'range' => ['required', Rule::in(array_map('strval', array_keys(self::RANGES)))],
        ];
    }

    public function onlyMine(): bool
    {
        return $this->validated('doctor') === 'mine';
    }

    /**
     * Anzahl Tage oder null für alle geplanten Termine.
     */
    public function days(): ?int
    {
        $range = (string) $this->validated('range');

        return $range === 'all' ? null : (int) $range;
    }

    /**
     * @return array{doctor: string, range: string}
     */
    public function filters(): array
    {
        return [
            'doctor' => (string) $this->validated('doctor'),
            'range' => (string) $this->validated('range'),
        ];
    }
}
