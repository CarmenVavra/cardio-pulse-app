<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationTest extends TestCase
{
    use RefreshDatabase;

    private function medication(Patient $patient, array $attributes = []): Medication
    {
        return $patient->medications()->create([
            'name' => 'Ramipril',
            'dose' => '10 mg',
            'schedule' => '1–0–0',
            ...$attributes,
        ]);
    }

    /* ------------------------------------------------------------ Arzt */

    public function test_doctor_adds_medication(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();

        $this->actingAs($doctor)
            ->post(route('patients.medications.store', $patient), [
                'name' => 'Amlodipin', 'dose' => '5 mg', 'morning' => '0', 'noon' => '0', 'evening' => '1',
            ])->assertSessionHas('status');

        $medication = $patient->medications()->firstOrFail();
        $this->assertSame('0–0–1', $medication->schedule);
        $this->assertSame($doctor->id, $medication->updated_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'medication.created', 'user_id' => $doctor->id]);

        $this->get(route('patients.show', $patient))->assertSee('Amlodipin')->assertSee('0–0–1');
    }

    public function test_doctor_updates_and_deletes_medication(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();
        $medication = $this->medication($patient);

        $this->actingAs($doctor)
            ->put(route('patients.medications.update', [$patient, $medication]), [
                'name' => 'Ramipril', 'dose' => '5 mg', 'morning' => '1', 'noon' => '0', 'evening' => '1',
            ])->assertSessionHas('status');

        $this->assertSame('5 mg', $medication->fresh()->dose);
        $this->assertSame('1–0–1', $medication->fresh()->schedule);

        $this->delete(route('patients.medications.destroy', [$patient, $medication]))->assertSessionHas('status');

        $this->assertModelMissing($medication);
        $this->assertDatabaseHas('audit_logs', ['action' => 'medication.deleted']);
    }

    public function test_medication_must_belong_to_patient_in_url(): void
    {
        $patient = $this->patient();
        $foreign = $this->medication($this->patient());

        $this->actingAs($this->staff())
            ->delete(route('patients.medications.destroy', [$patient, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_medication_input_is_validated(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->staff())
            ->post(route('patients.medications.store', $patient), [
                'name' => '', 'dose' => '', 'morning' => '7', 'noon' => '0', 'evening' => '0',
            ])->assertSessionHasErrors(['name', 'dose', 'morning']);

        $this->assertDatabaseCount('medications', 0);
    }

    public function test_doctor_sees_changes_made_by_patient(): void
    {
        $patient = $this->patient();
        $this->medication($patient, ['updated_by' => $patient->user_id]);

        $this->actingAs($this->staff())
            ->get(route('patients.show', $patient))
            ->assertSee('vom Patienten geändert');
    }

    /* ------------------------------------------------------------ Patient */

    public function test_patient_sees_medication_page(): void
    {
        $patient = $this->patient();
        $this->medication($patient);

        $this->actingAs($patient->user)
            ->get(route('patient.medications.index'))
            ->assertOk()
            ->assertSee('Meine Medikation')
            ->assertSee('Ramipril')
            ->assertSee('Medikament hinzufügen');

        $this->get(route('patient.home'))->assertSee(route('patient.medications.index'));
    }

    public function test_patient_adds_updates_and_deletes_own_medication(): void
    {
        $patient = $this->patient();
        $user = $patient->user;

        $this->actingAs($user)
            ->post(route('patient.medications.store'), [
                'name' => 'Bisoprolol', 'dose' => '2,5 mg', 'morning' => '½', 'noon' => '0', 'evening' => '0',
            ])->assertRedirect(route('patient.medications.index'));

        $medication = $patient->medications()->firstOrFail();
        $this->assertSame('½–0–0', $medication->schedule);
        $this->assertSame($user->id, $medication->updated_by);

        $this->put(route('patient.medications.update', $medication), [
            'name' => 'Bisoprolol', 'dose' => '5 mg', 'morning' => '1', 'noon' => '0', 'evening' => '0',
        ])->assertRedirect(route('patient.medications.index'));
        $this->assertSame('5 mg', $medication->fresh()->dose);

        $this->delete(route('patient.medications.destroy', $medication))->assertRedirect(route('patient.medications.index'));
        $this->assertModelMissing($medication);
        $this->assertDatabaseHas('audit_logs', ['action' => 'medication.deleted', 'user_id' => $user->id]);
    }

    public function test_patient_cannot_change_foreign_medication(): void
    {
        $foreign = $this->medication($this->patient());

        $this->actingAs($this->patient()->user);

        $this->put(route('patient.medications.update', $foreign), [
            'name' => 'X', 'dose' => '1 mg', 'morning' => '1', 'noon' => '0', 'evening' => '0',
        ])->assertStatus(403);
        $this->delete(route('patient.medications.destroy', $foreign))->assertStatus(403);

        $this->assertSame('Ramipril', $foreign->fresh()->name);
    }

    public function test_patient_cannot_use_hospital_medication_routes(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)
            ->post(route('patients.medications.store', $patient), [
                'name' => 'X', 'dose' => '1 mg', 'morning' => '1', 'noon' => '0', 'evening' => '0',
            ])->assertStatus(403);
    }
}
