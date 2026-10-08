<?php

namespace App\Policies;

use App\Models\Measurement;
use App\Models\User;

class MeasurementPolicy
{
    /**
     * Personal sieht alle Messungen, Patienten nur die eigenen.
     */
    public function view(User $user, Measurement $measurement): bool
    {
        return $user->isStaff() || $user->patient?->id === $measurement->patient_id;
    }

    public function update(User $user, Measurement $measurement): bool
    {
        return $user->patient?->id === $measurement->patient_id;
    }
}
