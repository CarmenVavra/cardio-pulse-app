<?php

namespace App\Services;

use App\Enums\AlarmType;
use App\Enums\SosResponse;
use App\Models\Alarm;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Support\Facades\Cache;

/**
 * Notfalltaste (SOS) der Patienten-App: Alarm im Krankenhaus auslösen, Standort
 * mitschicken (nur mit Einwilligung), Fehlalarm melden. Den Notruf 144 wählt der
 * Patient selbst auf seinem Handy – die App kann keinen Rettungsdienst alarmieren.
 * Kann er nicht telefonieren, bittet er das Krankenhaus darum (respond()).
 */
class EmergencyService
{
    /**
     * Notfalltaste gedrückt. Pro Patient gibt es höchstens einen offenen SOS-Alarm:
     * Drückt er erneut, bekommt der bestehende Alarm nur den neuesten Standort.
     *
     * @param  array{lat: float, lng: float, accuracy: int|null}|null  $location
     */
    public function trigger(Patient $patient, ?array $location = null): Alarm
    {
        $location = $patient->location_consent_at !== null ? $location : null;

        // Sperre gegen doppeltes Auslösen durch schnelles Mehrfachtippen.
        return Cache::lock('sos:'.$patient->id, 10)->block(5, function () use ($patient, $location) {
            $alarm = $patient->alarms()->open()->sos()->first();

            if ($alarm !== null) {
                $alarm->update(array_filter([
                    'location' => $location,
                    'located_at' => $location !== null ? now() : null,
                ]) + ['false_alarm_at' => null]);

                AuditLog::record('alarm.sos_repeated', $alarm, ['location' => $location !== null]);

                return $alarm;
            }

            $alarm = Alarm::create([
                'patient_id' => $patient->id,
                'type' => AlarmType::Sos,
                'location' => $location,
                'located_at' => $location !== null ? now() : null,
                'triggered_at' => now(),
            ]);

            AuditLog::record('alarm.sos_triggered', $alarm, ['location' => $location !== null]);

            return $alarm;
        });
    }

    /**
     * Standort nachreichen (die Ortung dauert oft länger als das Auslösen).
     *
     * @param  array{lat: float, lng: float, accuracy: int|null}  $location
     */
    public function updateLocation(Patient $patient, array $location): ?Alarm
    {
        $alarm = $this->openAlarm($patient);
        if ($alarm === null || $patient->location_consent_at === null) {
            return $alarm;
        }

        $first = $alarm->location === null;
        $alarm->update(['location' => $location, 'located_at' => now()]);

        if ($first) {
            AuditLog::record('alarm.location_received', $alarm, ['location' => true]);
        }

        return $alarm;
    }

    /**
     * Patient meldet „Fehlalarm“. Der Alarm bleibt offen – das Krankenhaus muss ihn
     * trotzdem quittieren (und meldet sich im Zweifel beim Patienten).
     */
    public function markFalseAlarm(Patient $patient): ?Alarm
    {
        $alarm = $this->openAlarm($patient);
        if ($alarm === null || $alarm->false_alarm_at !== null) {
            return $alarm;
        }

        $alarm->update(['false_alarm_at' => now()]);
        AuditLog::record('alarm.false_alarm', $alarm);

        return $alarm;
    }

    /**
     * Patient gibt an, wer die Rettung ruft: er selbst oder das Krankenhaus. Er kann
     * jederzeit wechseln (z. B. wenn der eigene Anruf nicht klappt); „Krankenhaus soll
     * rufen“ nimmt einen gemeldeten Fehlalarm zurück.
     */
    public function respond(Patient $patient, SosResponse $response): ?Alarm
    {
        $alarm = $this->openAlarm($patient);
        if ($alarm === null || ($alarm->patient_response === $response && $alarm->false_alarm_at === null)) {
            return $alarm;
        }

        $alarm->update([
            'patient_response' => $response,
            'responded_at' => now(),
        ] + ($response === SosResponse::HospitalCalls ? ['false_alarm_at' => null] : []));

        AuditLog::record(match ($response) {
            SosResponse::SelfCalling => 'alarm.patient_calls_rescue',
            SosResponse::HospitalCalls => 'alarm.patient_requests_rescue',
        }, $alarm);

        return $alarm;
    }

    public function openAlarm(Patient $patient): ?Alarm
    {
        return $patient->alarms()->open()->sos()->with(['claimedBy', 'rescueCalledBy'])->latest('triggered_at')->first();
    }

    /**
     * Zuletzt bearbeiteter Notruf der letzten Stunde – zur Rückmeldung in der App.
     */
    public function recentlyHandled(Patient $patient): ?Alarm
    {
        return $patient->alarms()
            ->sos()
            ->whereNotNull('acknowledged_at')
            ->where('acknowledged_at', '>=', now()->subHour())
            ->with('acknowledgedBy')
            ->latest('acknowledged_at')
            ->first();
    }

    /**
     * Einwilligung, im Notfall den Standort zu übermitteln – jederzeit widerrufbar.
     * Beim Widerruf wird ein schon übermittelter Standort sofort gelöscht.
     */
    public function setLocationConsent(Patient $patient, bool $consent): void
    {
        if ($consent === ($patient->location_consent_at !== null)) {
            return;
        }

        $patient->update(['location_consent_at' => $consent ? now() : null]);

        if (! $consent) {
            $patient->alarms()->open()->sos()->whereNotNull('location')->get()
                ->each(fn (Alarm $alarm) => $alarm->update(['location' => null, 'located_at' => null]));
        }

        AuditLog::record($consent ? 'patient.location_consent_given' : 'patient.location_consent_withdrawn', $patient);
    }
}
