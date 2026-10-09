<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Measurement;
use App\Models\Patient;
use Illuminate\Support\Carbon;

/**
 * HL7 FHIR R4 Export (KIS-Anbindung): Patient, Blutdruck-Observations und Termine der
 * Videosprechstunde (Appointment) als Bundle.
 *
 * LOINC: 85354-9 Blutdruck-Panel · 8480-6 systolisch · 8462-4 diastolisch · 8867-4 Puls.
 */
class FhirExporter
{
    private const LOINC = 'http://loinc.org';

    private const UCUM = 'http://unitsofmeasure.org';

    /**
     * @return array<string, mixed>
     */
    public function bundle(Patient $patient, Carbon $from, Carbon $to): array
    {
        $measurements = $patient->measurements()
            ->whereBetween('measured_at', [$from, $to])
            ->orderBy('measured_at')
            ->get();

        $entries = [[
            'fullUrl' => 'urn:uuid:patient-'.$patient->patient_number,
            'resource' => $this->patient($patient),
        ]];

        foreach ($measurements as $measurement) {
            $entries[] = [
                'fullUrl' => 'urn:uuid:bp-'.$measurement->id,
                'resource' => $this->bloodPressure($patient, $measurement),
            ];

            if ($measurement->pulse !== null) {
                $entries[] = [
                    'fullUrl' => 'urn:uuid:hr-'.$measurement->id,
                    'resource' => $this->heartRate($patient, $measurement),
                ];
            }
        }

        $appointments = $patient->appointments()
            ->whereBetween('starts_at', [$from, $to])
            ->with('doctor')
            ->orderBy('starts_at')
            ->get();

        foreach ($appointments as $appointment) {
            $entries[] = [
                'fullUrl' => 'urn:uuid:appointment-'.$appointment->id,
                'resource' => $this->appointment($patient, $appointment),
            ];
        }

        return [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'timestamp' => now()->toIso8601String(),
            'total' => count($entries),
            'entry' => $entries,
        ];
    }

    /**
     * Videosprechstunde als FHIR Appointment (virtueller Termin, ServiceType „Videosprechstunde“).
     *
     * @return array<string, mixed>
     */
    private function appointment(Patient $patient, Appointment $appointment): array
    {
        $participants = [[
            'actor' => ['reference' => 'urn:uuid:patient-'.$patient->patient_number, 'display' => $patient->fullName()],
            'status' => 'accepted',
        ]];

        if ($appointment->doctor !== null) {
            $participants[] = [
                'actor' => ['display' => $appointment->doctor->displayName()],
                'status' => 'accepted',
            ];
        }

        return array_filter([
            'resourceType' => 'Appointment',
            'id' => 'appointment-'.$appointment->id,
            'status' => $appointment->status->value,
            'serviceType' => [['text' => 'Videosprechstunde']],
            'appointmentType' => [
                'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0276', 'code' => 'FOLLOWUP', 'display' => 'A follow up visit from a previous appointment']],
            ],
            'description' => $appointment->reason,
            'start' => $appointment->starts_at->toIso8601String(),
            'end' => $appointment->ends_at->toIso8601String(),
            'minutesDuration' => $appointment->durationMinutes(),
            'participant' => $participants,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function patient(Patient $patient): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => $patient->patient_number,
            'identifier' => [['system' => 'urn:cardiopulse:patient', 'value' => $patient->patient_number]],
            'name' => [['family' => $patient->last_name, 'given' => [$patient->first_name]]],
            'birthDate' => $patient->birth_date->toDateString(),
            'telecom' => [['system' => 'phone', 'value' => $patient->phone]],
            'address' => [[
                'line' => [$patient->street],
                'postalCode' => $patient->postal_code,
                'city' => $patient->city,
                'country' => 'DE',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bloodPressure(Patient $patient, Measurement $measurement): array
    {
        return [
            'resourceType' => 'Observation',
            'id' => 'bp-'.$measurement->id,
            'status' => 'final',
            'category' => [$this->vitalSigns()],
            'code' => ['coding' => [['system' => self::LOINC, 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional']]],
            'subject' => ['reference' => 'Patient/'.$patient->patient_number],
            'effectiveDateTime' => $measurement->measured_at->toIso8601String(),
            'interpretation' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation',
                    'code' => $measurement->status->fhirInterpretation(),
                    'display' => $measurement->status->fhirInterpretationDisplay(),
                ]],
                'text' => $measurement->status->colorName(),
            ]],
            'component' => [
                $this->component('8480-6', 'Systolic blood pressure', $measurement->systolic),
                $this->component('8462-4', 'Diastolic blood pressure', $measurement->diastolic),
            ],
            'note' => $measurement->symptoms ? [['text' => 'Symptome: '.$measurement->symptomLabels()]] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function heartRate(Patient $patient, Measurement $measurement): array
    {
        return [
            'resourceType' => 'Observation',
            'id' => 'hr-'.$measurement->id,
            'status' => 'final',
            'category' => [$this->vitalSigns()],
            'code' => ['coding' => [['system' => self::LOINC, 'code' => '8867-4', 'display' => 'Heart rate']]],
            'subject' => ['reference' => 'Patient/'.$patient->patient_number],
            'effectiveDateTime' => $measurement->measured_at->toIso8601String(),
            'valueQuantity' => ['value' => $measurement->pulse, 'unit' => '/min', 'system' => self::UCUM, 'code' => '/min'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function component(string $code, string $display, int $value): array
    {
        return [
            'code' => ['coding' => [['system' => self::LOINC, 'code' => $code, 'display' => $display]]],
            'valueQuantity' => ['value' => $value, 'unit' => 'mmHg', 'system' => self::UCUM, 'code' => 'mm[Hg]'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vitalSigns(): array
    {
        return ['coding' => [[
            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
            'code' => 'vital-signs',
            'display' => 'Vital Signs',
        ]]];
    }
}
