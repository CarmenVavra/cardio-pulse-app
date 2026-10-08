<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    /**
     * Personal hat Zugriff auf alle Anrufe, Patienten nur auf eigene.
     */
    public function participate(User $user, Call $call): bool
    {
        return $user->isStaff() || $user->patient?->id === $call->patient_id;
    }
}
