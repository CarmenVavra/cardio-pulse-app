<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Notfalltaste und Standort-Nachreichung der Patienten-App. Der Standort ist optional
 * (ohne Einwilligung, ohne GPS oder bei verweigerter Freigabe kommt keiner mit).
 */
class SosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->patient !== null;
    }

    /**
     * Beim Auslösen darf ein unbrauchbarer Standort den Alarm nie verhindern –
     * ungültige Koordinaten werden dann einfach weggelassen.
     */
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('patient.sos.location')) {
            return;
        }

        $lat = $this->input('lat');
        $lng = $this->input('lng');
        $valid = is_numeric($lat) && is_numeric($lng)
            && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
        $accuracy = $this->input('accuracy');

        $this->merge([
            'lat' => $valid ? $lat : null,
            'lng' => $valid ? $lng : null,
            'accuracy' => $valid && is_numeric($accuracy) && $accuracy >= 0 && $accuracy <= 100000 ? $accuracy : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->routeIs('patient.sos.location') ? 'required' : 'nullable';

        return [
            'lat' => [$required, 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => [$required, 'required_with:lat', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array{lat: float, lng: float, accuracy: int|null}|null
     */
    public function location(): ?array
    {
        $lat = $this->validated('lat');
        $lng = $this->validated('lng');

        if ($lat === null || $lng === null) {
            return null;
        }

        $accuracy = $this->validated('accuracy');

        return [
            'lat' => round((float) $lat, 6),
            'lng' => round((float) $lng, 6),
            'accuracy' => $accuracy !== null ? (int) round((float) $accuracy) : null,
        ];
    }
}
