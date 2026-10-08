<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\User;

/**
 * Medikation erfassen, ändern, löschen – von Arzt oder Patient, jeweils protokolliert.
 */
class MedicationService
{
    /**
     * @param  array{name: string, dose: string, schedule: string}  $data
     */
    public function create(Patient $patient, array $data, User $by): Medication
    {
        $medication = $patient->medications()->create([...$data, 'updated_by' => $by->id]);

        AuditLog::record('medication.created', $medication, ['patient_id' => $patient->id, ...$data], $by);

        return $medication;
    }

    /**
     * @param  array{name: string, dose: string, schedule: string}  $data
     */
    public function update(Medication $medication, array $data, User $by): Medication
    {
        $before = $medication->only(['name', 'dose', 'schedule']);

        $medication->update([...$data, 'updated_by' => $by->id]);

        AuditLog::record('medication.updated', $medication, [
            'patient_id' => $medication->patient_id,
            'before' => $before,
            'after' => $data,
        ], $by);

        return $medication;
    }

    public function delete(Medication $medication, User $by): void
    {
        AuditLog::record('medication.deleted', $medication, [
            'patient_id' => $medication->patient_id,
            ...$medication->only(['name', 'dose', 'schedule']),
        ], $by);

        $medication->delete();
    }
}
