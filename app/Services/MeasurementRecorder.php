<?php

namespace App\Services;

use App\Enums\BloodPressureStatus;
use App\Models\Alarm;
use App\Models\AuditLog;
use App\Models\Measurement;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Speichert eine Blutdruckmessung, klassifiziert sie (Ampel) und löst bei
 * gefährlich hohen Werten einen Alarm im Krankenhaus aus.
 */
class MeasurementRecorder
{
    /**
     * @param  array{systolic: int|string, diastolic: int|string, pulse?: int|string|null, method?: string|null, symptoms?: list<string>|null, rested?: bool|null, medication_taken?: bool|null}  $data
     */
    public function record(Patient $patient, array $data, ?Carbon $measuredAt = null): Measurement
    {
        $systolic = (int) $data['systolic'];
        $diastolic = (int) $data['diastolic'];
        $status = BloodPressureStatus::classify($systolic, $diastolic);
        $measuredAt ??= now();

        return DB::transaction(function () use ($patient, $data, $systolic, $diastolic, $status, $measuredAt) {
            $measurement = $patient->measurements()->create([
                'systolic' => $systolic,
                'diastolic' => $diastolic,
                'pulse' => isset($data['pulse']) && $data['pulse'] !== '' ? (int) $data['pulse'] : null,
                'status' => $status,
                'method' => $data['method'] ?? 'manual',
                'symptoms' => array_values(array_diff($data['symptoms'] ?? [], ['keine'])),
                'rested' => (bool) ($data['rested'] ?? false),
                'medication_taken' => (bool) ($data['medication_taken'] ?? false),
                'measured_at' => $measuredAt,
            ]);

            if ($status === BloodPressureStatus::Red) {
                Alarm::create([
                    'patient_id' => $patient->id,
                    'measurement_id' => $measurement->id,
                    'triggered_at' => $measuredAt,
                ]);
            }

            AuditLog::record('measurement.uploaded', $measurement, [
                'reading' => $measurement->reading(),
                'status' => $status->value,
                'fhir_interpretation' => $status->fhirInterpretation(),
            ]);

            return $measurement;
        });
    }

    /**
     * Patient bestätigt im Notfall-Screen, dass keine akuten Beschwerden bestehen.
     */
    public function confirmSymptomFree(Measurement $measurement): void
    {
        if ($measurement->symptom_free_confirmed_at !== null) {
            return;
        }

        $measurement->update(['symptom_free_confirmed_at' => now()]);

        AuditLog::record('measurement.symptom_free_confirmed', $measurement);
    }
}
