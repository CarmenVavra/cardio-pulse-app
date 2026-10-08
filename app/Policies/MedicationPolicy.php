<?php

namespace App\Policies;

use App\Models\Medication;
use App\Models\Patient;
use App\Models\User;

/**
 * Medikation pflegen: Personal für alle Patienten, Patienten nur die eigene.
 */
class MedicationPolicy
{
    public function create(User $user, Patient $patient): bool
    {
        return $user->isStaff() || $user->patient?->id === $patient->id;
    }

    public function update(User $user, Medication $medication): bool
    {
        return $user->isStaff() || $user->patient?->id === $medication->patient_id;
    }

    public function delete(User $user, Medication $medication): bool
    {
        return $this->update($user, $medication);
    }
}
