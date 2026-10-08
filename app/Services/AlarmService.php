<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AlarmService
{
    /**
     * Offene (nicht quittierte) Alarme, älteste zuerst.
     *
     * @return Collection<int, Alarm>
     */
    public function open(): Collection
    {
        return Alarm::query()
            ->open()
            ->whereHas('patient')
            ->with(['patient', 'measurement'])
            ->orderBy('triggered_at')
            ->get();
    }

    /**
     * Zuletzt quittierter Alarm der letzten 15 Minuten (für das Quittiert-Banner).
     */
    public function recentlyAcknowledged(): ?Alarm
    {
        return Alarm::query()
            ->whereNotNull('acknowledged_at')
            ->where('acknowledged_at', '>=', now()->subMinutes(15))
            ->whereHas('patient')
            ->with(['patient', 'acknowledgedBy'])
            ->latest('acknowledged_at')
            ->first();
    }

    /**
     * Pflicht-Quittierung: Benutzer + Zeitstempel (+ optionale Maßnahme) werden protokolliert.
     */
    public function acknowledge(Alarm $alarm, User $user, ?string $note = null): Alarm
    {
        if (! $alarm->isOpen()) {
            return $alarm;
        }

        $alarm->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
            'action_note' => $note,
        ]);

        AuditLog::record('alarm.acknowledged', $alarm, [
            'patient_id' => $alarm->patient_id,
            'measurement_id' => $alarm->measurement_id,
            'note' => $note,
            'seconds_open' => (int) $alarm->triggered_at->diffInSeconds(now(), true),
        ], $user);

        return $alarm;
    }
}
