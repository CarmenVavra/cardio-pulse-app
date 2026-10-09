<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\ClinicMessageNotification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Chat zwischen Klinik und Patient (keine Notfall-Kommunikation – dafür Notruf bzw. Anruf).
 */
class MessageService
{
    public const THREAD_LIMIT = 20;

    public function sendToPatient(Patient $patient, User $staff, string $body): Message
    {
        $message = $patient->messages()->create([
            'user_id' => $staff->id,
            'from_patient' => false,
            'body' => $body,
        ]);

        AuditLog::record('message.sent', $message, ['patient_id' => $patient->id]);

        $patient->loadMissing('user')->user?->notify(new ClinicMessageNotification);

        return $message;
    }

    public function sendToClinic(Patient $patient, string $body): Message
    {
        $message = $patient->messages()->create([
            'user_id' => $patient->user_id,
            'from_patient' => true,
            'body' => $body,
        ]);

        AuditLog::record('message.received', $message, ['patient_id' => $patient->id]);

        return $message;
    }

    /**
     * Letzte Nachrichten in zeitlicher Reihenfolge (älteste zuerst).
     *
     * @return Collection<int, Message>
     */
    public function thread(Patient $patient, int $limit = self::THREAD_LIMIT): Collection
    {
        return $patient->messages()
            ->with('user')
            ->latest()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(fn (Message $message) => $message->setRelation('patient', $patient))
            ->reverse()
            ->values();
    }

    /**
     * Nachrichten der Klinik als vom Patienten gelesen markieren.
     */
    public function markReadByPatient(Patient $patient): void
    {
        $patient->messages()->fromClinic()->unread()->update(['read_at' => now()]);
    }

    /**
     * Nachrichten des Patienten als von der Klinik gelesen markieren.
     */
    public function markReadByClinic(Patient $patient): void
    {
        $patient->messages()->fromPatient()->unread()->update(['read_at' => now()]);
    }
}
