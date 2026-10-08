<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(User $doctor, array $overrides = []): array
    {
        return [
            'first_name' => 'Greta',
            'last_name' => 'Lindqvist',
            'birth_date' => '1958-04-12',
            'street' => 'Mühlenkamp 3',
            'postal_code' => '22303',
            'city' => 'Hamburg',
            'phone' => '0171 555 0101',
            'diagnosis' => 'Hypertonie Grad 1',
            'gp_name' => 'Dr. Lenz',
            'doctor_id' => $doctor->id,
            'email' => 'greta.lindqvist@cardiopulse.test',
            'password' => 'sicher-123',
            'password_confirmation' => 'sicher-123',
            ...$overrides,
        ];
    }

    public function test_create_form_is_rendered(): void
    {
        $this->actingAs($this->staff())
            ->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Patient anlegen')
            ->assertSee('CP-10001')
            ->assertSee('App-Zugang');
    }

    public function test_doctor_creates_patient_with_app_access(): void
    {
        $doctor = $this->staff();
        $this->patient(['patient_number' => 'CP-10812']);

        $response = $this->actingAs($doctor)->post(route('patients.store'), $this->payload($doctor));

        $patient = Patient::query()->where('last_name', 'Lindqvist')->firstOrFail();
        $response->assertRedirect(route('patients.show', $patient));

        $this->assertSame('CP-10813', $patient->patient_number);
        $this->assertSame($doctor->id, $patient->doctor_id);
        $this->assertSame('1958-04-12', $patient->birth_date->toDateString());
        $this->assertSame(UserRole::Patient, $patient->user->role);
        $this->assertSame('greta.lindqvist@cardiopulse.test', $patient->user->email);
        $this->assertTrue(Hash::check('sicher-123', $patient->user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.created', 'user_id' => $doctor->id]);

        // Neuer Patient kann sich in der App anmelden und erscheint auf dem Board.
        $this->post('/logout');
        $this->post('/app/login', ['email' => 'greta.lindqvist@cardiopulse.test', 'password' => 'sicher-123'])
            ->assertRedirect(route('patient.home'));

        $this->actingAs($doctor)->get('/ueberwachung')->assertSee('Greta Lindqvist')->assertSee('Keine Daten');
    }

    public function test_create_validates_input(): void
    {
        $doctor = $this->staff();
        $existing = $this->patient();

        $this->actingAs($doctor)
            ->post(route('patients.store'), $this->payload($doctor, [
                'first_name' => '',
                'birth_date' => now()->addDay()->toDateString(),
                'postal_code' => 'ABC',
                'email' => $existing->user->email,
                'password_confirmation' => 'anders',
            ]))
            ->assertSessionHasErrors(['first_name', 'birth_date', 'postal_code', 'email', 'password']);

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_treating_doctor_must_be_staff(): void
    {
        $doctor = $this->staff();
        $patientUser = $this->patient()->user;

        $this->actingAs($doctor)
            ->post(route('patients.store'), $this->payload($doctor, ['doctor_id' => $patientUser->id]))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_doctor_edits_patient_without_changing_password(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient(['first_name' => 'Karin', 'last_name' => 'Hofmann']);
        $oldHash = $patient->user->password;

        $this->actingAs($doctor)
            ->get(route('patients.edit', $patient))
            ->assertOk()
            ->assertSee('Karin Hofmann bearbeiten')
            ->assertSee($patient->user->email);

        $this->put(route('patients.update', $patient), $this->payload($doctor, [
            'first_name' => 'Karin',
            'last_name' => 'Hofmann-Berg',
            'city' => 'Pinneberg',
            'email' => 'karin.hofmann@cardiopulse.test',
            'password' => '',
            'password_confirmation' => '',
        ]))->assertRedirect(route('patients.show', $patient));

        $patient->refresh();
        $this->assertSame('Hofmann-Berg', $patient->last_name);
        $this->assertSame('Pinneberg', $patient->city);
        $this->assertSame('karin.hofmann@cardiopulse.test', $patient->user->email);
        $this->assertSame('Karin Hofmann-Berg', $patient->user->name);
        $this->assertSame($oldHash, $patient->user->password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.updated']);
    }

    public function test_doctor_can_reset_patient_password(): void
    {
        $doctor = $this->staff();
        $patient = $this->patient();

        $this->actingAs($doctor)->put(route('patients.update', $patient), $this->payload($doctor, [
            'email' => $patient->user->email,
            'password' => 'neues-passwort',
            'password_confirmation' => 'neues-passwort',
        ]))->assertRedirect();

        $this->assertTrue(Hash::check('neues-passwort', $patient->user->fresh()->password));
    }

    public function test_patient_cannot_manage_patients(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)->get(route('patients.create'))->assertStatus(403);
        $this->actingAs($patient->user)->get(route('patients.edit', $patient))->assertStatus(403);
    }
}
