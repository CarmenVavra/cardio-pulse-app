<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AlarmService
{
    /**
     * Offene (nicht quittierte) Alarme in der Reihenfolge, in der der Betrachter sie
     * abarbeiten soll: zuerst die von ihm selbst übernommenen, dann die noch freien
     * (Notfalltaste vor hohem Messwert), zuletzt die von Kollegen übernommenen –
     * jeweils der älteste zuerst. So landen gleichzeitige Notfälle bei verschiedenen Ärzten.
     *
     * @return Collection<int, Alarm>
     */
    public function open(?User $viewer = null): Collection
    {
        return Alarm::query()
            ->open()
            ->whereHas('patient')
            ->with(['patient', 'measurement', 'claimedBy'])
            ->get()
            ->sortBy([
                fn (Alarm $a, Alarm $b) => $this->rank($a, $viewer) <=> $this->rank($b, $viewer),
                fn (Alarm $a, Alarm $b) => $a->triggered_at <=> $b->triggered_at,
                fn (Alarm $a, Alarm $b) => $a->id <=> $b->id,
            ])
            ->values();
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
     * „Ich kümmere mich“: Ein Arzt übernimmt den Alarm, damit Kollegen den nächsten
     * Notfall bearbeiten. Atomar – übernehmen zwei gleichzeitig, gewinnt der erste.
     */
    public function claim(Alarm $alarm, User $user): Alarm
    {
        $claimed = Alarm::query()
            ->whereKey($alarm->id)
            ->open()
            ->whereNull('claimed_by')
            ->update(['claimed_by' => $user->id, 'claimed_at' => now()]);

        $alarm->refresh()->load('claimedBy');

        if ($claimed === 1) {
            AuditLog::record('alarm.claimed', $alarm, ['patient_id' => $alarm->patient_id], $user);
        }

        return $alarm;
    }

    /**
     * Pflicht-Quittierung: Benutzer + Zeitstempel (+ optionale Maßnahme) werden protokolliert.
     * Ein übermittelter Standort wird dabei gelöscht – er wird nur für den Notfall gebraucht.
     */
    public function acknowledge(Alarm $alarm, User $user, ?string $note = null): Alarm
    {
        if (! $alarm->isOpen()) {
            return $alarm;
        }

        $hadLocation = $alarm->location !== null;

        $alarm->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
            'action_note' => $note,
            'location' => null,
        ]);

        AuditLog::record('alarm.acknowledged', $alarm, [
            'type' => $alarm->type->value,
            'patient_id' => $alarm->patient_id,
            'measurement_id' => $alarm->measurement_id,
            'note' => $note,
            'location_deleted' => $hadLocation ?: null,
            'seconds_open' => (int) $alarm->triggered_at->diffInSeconds(now(), true),
        ], $user);

        return $alarm;
    }

    /**
     * 0 = vom Betrachter übernommen, 1/2 = frei (Notfalltaste/Messwert), 3 = von Kollegen übernommen.
     */
    private function rank(Alarm $alarm, ?User $viewer): int
    {
        if ($alarm->claimed_by !== null) {
            return $viewer !== null && $alarm->claimed_by === $viewer->id ? 0 : 3;
        }

        return $alarm->isSos() ? 1 : 2;
    }
}
