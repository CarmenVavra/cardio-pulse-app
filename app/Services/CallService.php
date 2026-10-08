<?php

namespace App\Services;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Patient;
use App\Models\User;

/**
 * Anruf-Lebenszyklus Arzt ↔ Patient (Signalisierung; Sprachkanal via VoIP/WebRTC).
 */
class CallService
{
    /**
     * Arzt ruft Patient an (D2/D4 "Anrufen", D3 "Patient anrufen").
     */
    public function callPatient(Patient $patient, User $doctor): Call
    {
        $this->closeOpenCalls($patient);

        $call = Call::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'measurement_id' => $patient->latestMeasurement()->value('id'),
            'direction' => CallDirection::ToPatient,
            'status' => CallStatus::Ringing,
        ]);

        AuditLog::record('call.started', $call, ['direction' => CallDirection::ToPatient->value], $doctor);

        return $call;
    }

    /**
     * Patient ruft seinen Arzt bzw. die Telemedizin-Zentrale an (M6).
     */
    public function callClinic(Patient $patient, bool $ownDoctor = true): Call
    {
        $this->closeOpenCalls($patient);

        $call = Call::create([
            'patient_id' => $patient->id,
            'user_id' => $ownDoctor ? $patient->doctor_id : null,
            'measurement_id' => $patient->latestMeasurement()->value('id'),
            'direction' => CallDirection::ToClinic,
            'status' => CallStatus::Ringing,
        ]);

        AuditLog::record('call.started', $call, ['direction' => CallDirection::ToClinic->value]);

        return $call;
    }

    public function answer(Call $call, ?User $staff = null): Call
    {
        if ($call->status !== CallStatus::Ringing) {
            return $call;
        }

        $call->update([
            'status' => CallStatus::Active,
            'answered_at' => now(),
            'user_id' => $call->user_id ?? $staff?->id,
        ]);

        AuditLog::record('call.answered', $call);

        return $call;
    }

    public function decline(Call $call): Call
    {
        if ($call->status !== CallStatus::Ringing) {
            return $call;
        }

        $call->update(['status' => CallStatus::Declined, 'ended_at' => now()]);

        AuditLog::record('call.declined', $call);

        return $call;
    }

    public function end(Call $call): Call
    {
        if (! $call->status->isOpen()) {
            return $call;
        }

        $call->update([
            'status' => $call->status === CallStatus::Ringing ? CallStatus::Declined : CallStatus::Ended,
            'ended_at' => now(),
        ]);

        AuditLog::record('call.ended', $call, ['duration_seconds' => $call->durationSeconds()]);

        return $call;
    }

    public function saveNote(Call $call, string $note): Call
    {
        $call->update(['note' => $note]);

        AuditLog::record('call.note_saved', $call);

        return $call;
    }

    private function closeOpenCalls(Patient $patient): void
    {
        $patient->calls()->open()->get()->each(fn (Call $call) => $this->end($call));
    }
}
