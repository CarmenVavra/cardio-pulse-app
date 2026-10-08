<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Models\Call;
use App\Models\Patient;
use App\Services\MeasurementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_offers_delete_section(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->staff())
            ->get(route('patients.edit', $patient))
            ->assertOk()
            ->assertSee('Patient löschen')
            ->assertSee('§ 630f BGB');
    }

    public function test_delete_requires_confirmation(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->staff())
            ->delete(route('patients.destroy', $patient))
            ->assertSessionHasErrors('confirm');

        $this->assertNotSoftDeleted($patient);
    }

    public function test_doctor_deletes_patient_but_data_is_retained(): void
    {
        $doctor = $this->staff();
        $remaining = $this->patient(['first_name' => 'Hans', 'last_name' => 'Becker']);
        $this->measurement($remaining, 125, 80);
        $patient = $this->patient(['first_name' => 'Greta', 'last_name' => 'Lindqvist']);
        $measurement = $this->measurement($patient, 150, 95);
        $patient->medications()->create(['name' => 'Ramipril', 'dose' => '5 mg', 'schedule' => '1–0–0']);

        $this->actingAs($doctor)
            ->delete(route('patients.destroy', $patient), ['confirm' => '1', 'reason' => 'Behandlung beendet'])
            ->assertRedirect(route('patients.index'));

        $this->assertSoftDeleted($patient);
        $this->assertModelExists($measurement);
        $this->assertDatabaseCount('medications', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.deleted', 'user_id' => $doctor->id]);

        // Statusmeldung überlebt die Weiterleitung auf den nächsten Patienten.
        $this->get(route('patients.index'))->assertRedirect(route('patients.show', $remaining));
        $this->get(route('patients.show', $remaining))->assertSee('wurde gelöscht');

        $this->get('/ueberwachung')->assertDontSee('Greta Lindqvist')->assertSee('Hans Becker');
        $this->get(route('patients.show', $patient))->assertNotFound();
    }

    public function test_deleted_patient_cannot_log_in_to_app(): void
    {
        $patient = $this->patient();
        $email = $patient->user->email;

        $this->actingAs($this->staff())->delete(route('patients.destroy', $patient), ['confirm' => '1']);
        $this->post('/logout');

        $this->post('/app/login', ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_open_alarm_and_calls_of_deleted_patient_no_longer_show(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124]);

        $this->actingAs($doctor)->post(route('calls.store', $patient));
        $call = Call::firstOrFail();

        $this->delete(route('patients.destroy', $patient), ['confirm' => '1']);

        $this->assertSame(CallStatus::Declined, $call->fresh()->status);

        $this->getJson('/ueberwachung/live')
            ->assertOk()
            ->assertJsonPath('open_alarms', 0)
            ->assertJsonPath('modal_html', null);

        $this->get('/anrufe')->assertOk()->assertDontSee('Josef Brandner');
        $this->get(route('calls.show', $call))->assertNotFound();
    }

    public function test_patient_numbers_of_deleted_patients_are_not_reused(): void
    {
        $patient = $this->patient(['patient_number' => 'CP-10900']);

        $this->actingAs($this->staff())->delete(route('patients.destroy', $patient), ['confirm' => '1']);

        $this->get(route('patients.create'))->assertSee('CP-10901');
        $this->assertSame(1, Patient::withTrashed()->count());
    }

    public function test_patient_cannot_delete_patients(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)
            ->delete(route('patients.destroy', $patient), ['confirm' => '1'])
            ->assertStatus(403);

        $this->assertNotSoftDeleted($patient);
    }
}
