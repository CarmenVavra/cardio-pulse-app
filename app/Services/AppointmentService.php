<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Termine für Videosprechstunden: vereinbaren, absagen, Gespräch starten.
 */
class AppointmentService
{
    public function __construct(
        private readonly CallService $calls,
        private readonly MessageService $messages,
    ) {}

    public function schedule(Patient $patient, User $doctor, Carbon $startsAt, int $minutes, ?string $reason, User $by): Appointment
    {
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($minutes),
            'reason' => $reason,
            'status' => AppointmentStatus::Booked,
        ]);

        AuditLog::record('appointment.scheduled', $appointment, [
            'starts_at' => $appointment->starts_at->format('d.m.Y H:i'),
            'minutes' => $minutes,
        ], $by);

        $this->notify($appointment, AppointmentNotification::SCHEDULED);

        return $appointment;
    }

    /**
     * Absage durch das Krankenhaus (Patient bekommt eine E-Mail) oder durch den Patienten
     * (das Krankenhaus bekommt eine Nachricht im Chat).
     */
    public function cancel(Appointment $appointment, User $by): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Booked) {
            return $appointment;
        }

        $byPatient = $by->isPatient();
        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_patient' => $byPatient,
        ]);

        AuditLog::record('appointment.cancelled', $appointment, ['by_patient' => $byPatient], $by);

        if ($byPatient) {
            $this->messages->sendToClinic($appointment->patient, 'Ich habe meinen Termin für die Videosprechstunde abgesagt: '.$appointment->when().'.');
        } else {
            $this->notify($appointment, AppointmentNotification::CANCELLED);
        }

        return $appointment;
    }

    /**
     * Videosprechstunde zum Termin starten: Der Arzt ruft den Patienten in der App an.
     */
    public function start(Appointment $appointment, User $doctor): Call
    {
        $call = $this->calls->callPatient($appointment->patient, $doctor);

        $appointment->update([
            'status' => AppointmentStatus::Fulfilled,
            'call_id' => $call->id,
        ]);

        AuditLog::record('appointment.started', $appointment, [], $doctor);

        return $call;
    }

    /**
     * Hat der Arzt in diesem Zeitraum schon einen Termin?
     */
    public function overlaps(User $doctor, Carbon $startsAt, Carbon $endsAt, ?int $ignoreId = null): bool
    {
        return Appointment::query()
            ->where('user_id', $doctor->id)
            ->where('status', AppointmentStatus::Booked)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Termine der nächsten Tage für die Übersicht „Anrufe & Termine“.
     *
     * @return Collection<int, Appointment>
     */
    public function upcoming(int $days = 14): Collection
    {
        return Appointment::query()
            ->upcoming()
            ->where('starts_at', '<=', now()->addDays($days)->endOfDay())
            ->whereHas('patient', fn ($query) => $query->withoutTrashed())
            ->with(['patient', 'doctor'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Beim Löschen eines Patienten offene Termine absagen (ohne E-Mail – das Konto ist gesperrt).
     */
    public function cancelAllFor(Patient $patient, User $by): void
    {
        $patient->appointments()->upcoming()->get()->each(function (Appointment $appointment) use ($by) {
            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
            AuditLog::record('appointment.cancelled', $appointment, ['reason' => 'Patient gelöscht'], $by);
        });
    }

    private function notify(Appointment $appointment, string $kind): void
    {
        $user = $appointment->patient?->user;
        if ($user === null) {
            return;
        }

        try {
            $user->notify(new AppointmentNotification($appointment, $kind));
        } catch (TransportExceptionInterface $e) {
            // Mailserver nicht erreichbar: Der Termin gilt trotzdem und steht in der App.
            report($e);
        }
    }
}
