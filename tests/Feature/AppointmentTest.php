<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CallStatus;
use App\Models\Appointment;
use App\Models\Call;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Services\AppointmentService;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Montag, 12.10.2026, 09:00 – feste Zeit für alle Prüfungen.
        $this->travelTo(Carbon::parse('2026-10-12 09:00'));
        Notification::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function form(User $doctor, array $overrides = []): array
    {
        return [
            'date' => '2026-10-14',
            'time' => '10:30',
            'duration' => 15,
            'doctor_id' => $doctor->id,
            'reason' => 'Besprechung Monatsbericht',
            ...$overrides,
        ];
    }

    private function appointment(Patient $patient, User $doctor, string $start = '2026-10-14 10:30', int $minutes = 15): Appointment
    {
        return app(AppointmentService::class)->schedule($patient, $doctor, Carbon::parse($start), $minutes, null, $doctor);
    }

    public function test_doctor_schedules_a_video_consultation_and_the_patient_gets_an_email(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient(['doctor_id' => $doctor->id]);

        $this->actingAs($doctor)
            ->post(route('patients.appointments.store', $patient), $this->form($doctor))
            ->assertRedirect(route('patients.show', $patient).'#termine')
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Mi., 14.10.2026 · 10:30–10:45 Uhr'));

        $appointment = Appointment::query()->sole();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(15, $appointment->durationMinutes());
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.scheduled', 'user_id' => $doctor->id]);

        Notification::assertSentTo($patient->user, AppointmentNotification::class, function (AppointmentNotification $notification) use ($patient) {
            $mail = $notification->toMail($patient->user);
            $text = implode(' ', $mail->introLines);

            return $mail->subject === 'CardioPulse: Termin für Ihre Videosprechstunde'
                && str_contains($text, 'Mi., 14.10.2026 · 10:30–10:45 Uhr mit Dr. Miriam Weber')
                && ! str_contains($text, 'Monatsbericht'); // Anlass bleibt intern
        });

        $this->get(route('patients.show', $patient))
            ->assertSee('Videosprechstunden')
            ->assertSee('Mi., 14.10.2026 · 10:30–10:45 Uhr')
            ->assertSee('Besprechung Monatsbericht')
            ->assertDontSee('Videosprechstunde starten');
    }

    public function test_appointments_must_be_in_the_future_and_must_not_overlap(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();
        $this->appointment($this->patient(), $doctor, '2026-10-14 10:30', 30);
        $this->actingAs($doctor);

        $this->post(route('patients.appointments.store', $patient), $this->form($doctor, ['date' => '2026-10-12', 'time' => '08:00']))
            ->assertSessionHasErrors(['time' => 'Der Termin muss in der Zukunft liegen.'], null, 'appointment');

        $this->post(route('patients.appointments.store', $patient), $this->form($doctor, ['time' => '10:45']))
            ->assertSessionHasErrors(['time' => 'Dr. Miriam Weber hat zu dieser Zeit bereits einen Termin.'], null, 'appointment');

        $this->post(route('patients.appointments.store', $patient), $this->form($doctor, ['duration' => 7]))
            ->assertSessionHasErrors('duration', null, 'appointment');

        // Direkt im Anschluss ist frei.
        $this->post(route('patients.appointments.store', $patient), $this->form($doctor, ['time' => '11:00']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_video_consultation_starts_at_appointment_time(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();
        $appointment = $this->appointment($patient, $doctor);
        $this->actingAs($doctor);

        $this->post(route('appointments.start', $appointment))->assertSessionHasErrors('appointment', null, 'appointment');
        $this->assertSame(0, Call::query()->count());

        $this->travelTo(Carbon::parse('2026-10-14 10:22'));
        $this->get(route('patients.show', $patient))->assertSee('Videosprechstunde starten');
        $this->get(route('calls.index'))->assertSee($patient->fullName())->assertSee('Starten');

        $response = $this->post(route('appointments.start', $appointment));
        $call = Call::query()->sole();
        $response->assertRedirect(route('calls.show', $call));

        $this->assertSame(CallStatus::Ringing, $call->status);
        $this->assertSame($patient->id, $call->patient_id);
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Fulfilled, $appointment->status);
        $this->assertSame($call->id, $appointment->call_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.started']);
    }

    public function test_calls_page_filters_appointments_by_doctor_and_range(): void
    {
        $doctor = $this->staff();
        $colleague = User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
        $mine = $this->patient(['first_name' => 'Anna', 'last_name' => 'Eigner']);
        $mineLater = $this->patient(['first_name' => 'Berta', 'last_name' => 'Spaeter']);
        $foreign = $this->patient(['first_name' => 'Carl', 'last_name' => 'Kollege']);
        $this->appointment($mine, $doctor, '2026-10-14 10:30');
        $this->appointment($mineLater, $doctor, '2026-12-01 09:00');
        $this->appointment($foreign, $colleague, '2026-10-15 10:30');
        $this->actingAs($doctor);

        // Voreinstellung: nur eigene Termine der nächsten 14 Tage.
        $this->get(route('calls.index'))
            ->assertOk()
            ->assertSee('Nur meine Termine · Nächste 14 Tage')
            ->assertSee('Anna Eigner')
            ->assertDontSee('Berta Spaeter')
            ->assertDontSee('Carl Kollege')
            ->assertSee('1 Termin');

        $this->get(route('calls.index', ['doctor' => 'all']))
            ->assertSee('Anna Eigner')
            ->assertSee('Carl Kollege')
            ->assertDontSee('Berta Spaeter');

        $this->get(route('calls.index', ['range' => '90']))
            ->assertSee('Anna Eigner')
            ->assertSee('Berta Spaeter')
            ->assertDontSee('Carl Kollege');

        $this->get(route('calls.index', ['doctor' => 'all', 'range' => 'all']))
            ->assertSee('Alle Ärzte · Alle geplanten')
            ->assertSee('3 Termine');

        // Ungültige Werte fallen auf die Voreinstellung zurück.
        $this->get(route('calls.index', ['doctor' => 'x', 'range' => '5000']))
            ->assertOk()
            ->assertSee('Nur meine Termine · Nächste 14 Tage');

        $this->actingAs($colleague)
            ->get(route('calls.index'))
            ->assertSee('Carl Kollege')
            ->assertDontSee('Anna Eigner');

        $this->actingAs(User::factory()->staff()->create(['username' => 'n.neu']))
            ->get(route('calls.index'))
            ->assertSee('Sie haben in diesem Zeitraum keine Videosprechstunden.')
            ->assertSee('Termine aller Ärzte anzeigen');
    }

    public function test_hospital_cancels_and_the_patient_is_informed(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();
        $appointment = $this->appointment($patient, $doctor);

        $this->actingAs($doctor)
            ->from(route('patients.show', $patient))
            ->post(route('appointments.cancel', $appointment))
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertFalse($appointment->fresh()->cancelled_by_patient);
        Notification::assertSentTo($patient->user, AppointmentNotification::class, fn (AppointmentNotification $n) => $n->kind === AppointmentNotification::CANCELLED);
        $this->get(route('patients.show', $patient))->assertSee('Abgesagt');
    }

    public function test_patient_sees_the_appointment_and_can_cancel_it(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient(['doctor_id' => $doctor->id]);
        $appointment = $this->appointment($patient, $doctor);
        $this->actingAs($patient->user);

        $this->get(route('patient.home'))
            ->assertSee('Nächste Videosprechstunde')
            ->assertSee('Mi., 14.10.2026 · 10:30–10:45 Uhr');
        $this->get(route('patient.doctor'))->assertSee('Termin absagen');

        $this->post(route('patient.appointments.cancel', $appointment))
            ->assertRedirect(route('patient.doctor'))
            ->assertSessionHas('status');

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertTrue($appointment->cancelled_by_patient);
        // Das Krankenhaus erfährt es über den Chat (Badge auf dem Board).
        $this->assertDatabaseHas('messages', ['patient_id' => $patient->id, 'from_patient' => true]);
        Notification::assertNotSentTo($patient->user, AppointmentNotification::class, fn (AppointmentNotification $n) => $n->kind === AppointmentNotification::CANCELLED);
    }

    public function test_patients_cannot_cancel_foreign_or_started_appointments(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();
        $other = $this->patient();
        $appointment = $this->appointment($patient, $doctor);

        $this->actingAs($other->user)->post(route('patient.appointments.cancel', $appointment))->assertForbidden();

        $this->travelTo(Carbon::parse('2026-10-14 10:31'));
        $this->actingAs($patient->user)->post(route('patient.appointments.cancel', $appointment))->assertForbidden();
        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_deleting_patient_or_doctor_takes_care_of_appointments(): void
    {
        $doctor = $this->staff();
        $colleague = User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
        $patient = $this->patient(['doctor_id' => $colleague->id]);
        $other = $this->patient(['doctor_id' => $colleague->id]);
        $forDeletedPatient = $this->appointment($patient, $colleague, '2026-10-14 10:30');
        $forColleague = $this->appointment($other, $colleague, '2026-10-15 10:30');

        app(PatientService::class)->delete($patient, $doctor);
        $this->assertSame(AppointmentStatus::Cancelled, $forDeletedPatient->fresh()->status);

        $this->actingAs($doctor)->delete(route('doctors.destroy', $colleague), ['confirm' => '1', 'replacement_id' => $doctor->id]);
        $this->assertSame($doctor->id, $forColleague->fresh()->user_id);
    }

    public function test_fhir_export_and_audit_log_include_appointments(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();
        $this->appointment($patient, $doctor, '2026-10-14 10:30', 20);
        $this->actingAs($doctor);

        $bundle = $this->get(route('patients.fhir', [$patient, 'month' => '2026-10']))->assertOk()->json();
        $appointment = collect($bundle['entry'])->firstWhere('resource.resourceType', 'Appointment')['resource'];
        $this->assertSame('booked', $appointment['status']);
        $this->assertSame(20, $appointment['minutesDuration']);
        $this->assertSame('Videosprechstunde', $appointment['serviceType'][0]['text']);

        $this->get(route('audit.index'))
            ->assertSee('Videosprechstunde vereinbart')
            ->assertSee('Termin 14.10.2026 10:30 · '.$patient->fullName());
    }
}
