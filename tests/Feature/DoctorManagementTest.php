<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\UserRole;
use App\Models\Alarm;
use App\Models\Call;
use App\Models\User;
use App\Services\AlarmService;
use App\Services\MeasurementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DoctorManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Dr.',
            'name' => 'Tobias Krause',
            'username' => 'T.Krause',
            'email' => 't.krause@klinikum-nord.test',
            'phone' => '040 1234 5672',
            'available_until' => '22:00',
            'password' => 'sicher-123',
            'password_confirmation' => 'sicher-123',
            'pin' => '654321',
            ...$overrides,
        ];
    }

    private function colleague(string $username = 't.krause', string $name = 'Tobias Krause'): User
    {
        return User::factory()->staff()->create(['username' => $username, 'name' => $name]);
    }

    public function test_doctor_list_is_shown_in_navigation(): void
    {
        $this->colleague();

        $this->actingAs($this->staff())
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('Ärzte')
            ->assertSee('Dr. Tobias Krause')
            ->assertSee('Dr. Miriam Weber')
            ->assertSee('(Sie)')
            ->assertSee('Arzt anlegen');
    }

    public function test_doctor_creates_new_doctor_who_can_log_in(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin)
            ->post(route('doctors.store'), $this->payload())
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('status', 'Dr. Tobias Krause wurde angelegt.');

        $doctor = User::query()->where('username', 't.krause')->firstOrFail();
        $this->assertSame(UserRole::Staff, $doctor->role);
        $this->assertSame('22:00', $doctor->available_until);
        $this->assertTrue(Hash::check('654321', $doctor->pin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.created', 'user_id' => $admin->id]);

        $this->post('/logout');
        $this->post('/login', ['username' => 't.krause', 'password' => 'sicher-123', 'department' => 'telemonitoring'])
            ->assertRedirect(route('board'));
        $this->assertAuthenticatedAs($doctor);
    }

    public function test_create_validates_input(): void
    {
        $this->actingAs($this->staff())
            ->post(route('doctors.store'), $this->payload([
                'name' => '',
                'username' => 'm.weber',
                'email' => 'kein-email',
                'available_until' => '25:00',
                'password_confirmation' => 'anders',
                'pin' => '12ab',
            ]))
            ->assertSessionHasErrors(['name', 'username', 'email', 'available_until', 'password', 'pin']);

        $this->assertSame(1, User::query()->where('role', UserRole::Staff)->count());
    }

    public function test_doctor_edits_colleague_and_keeps_password(): void
    {
        $colleague = $this->colleague();
        $oldPassword = $colleague->password;

        $this->actingAs($this->staff())
            ->get(route('doctors.edit', $colleague))
            ->assertOk()
            ->assertSee('Dr. Tobias Krause bearbeiten')
            ->assertSee('Arzt löschen');

        $this->put(route('doctors.update', $colleague), $this->payload([
            'title' => 'Prof. Dr.',
            'username' => 't.krause',
            'email' => 'krause@klinikum-nord.test',
            'password' => '',
            'password_confirmation' => '',
            'pin' => '',
        ]))->assertRedirect(route('doctors.index'));

        $colleague->refresh();
        $this->assertSame('Prof. Dr.', $colleague->title);
        $this->assertSame('krause@klinikum-nord.test', $colleague->email);
        $this->assertSame($oldPassword, $colleague->password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.updated']);
    }

    public function test_deleting_doctor_reassigns_patients_and_blocks_login(): void
    {
        $admin = $this->staff();
        $colleague = $this->colleague();
        $patients = collect([$this->patient(['doctor_id' => $colleague->id]), $this->patient(['doctor_id' => $colleague->id])]);

        $this->actingAs($admin)
            ->delete(route('doctors.destroy', $colleague), ['confirm' => '1'])
            ->assertSessionHasErrors('replacement_id');

        $this->delete(route('doctors.destroy', $colleague), ['confirm' => '1', 'replacement_id' => $admin->id])
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('status', 'Dr. Tobias Krause wurde gelöscht. 2 Patienten wurden Dr. Miriam Weber zugewiesen.');

        $this->assertSoftDeleted($colleague);
        $patients->each(fn ($patient) => $this->assertSame($admin->id, $patient->fresh()->doctor_id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.deleted', 'user_id' => $admin->id]);

        // Die Erfolgsmeldung nennt den Namen – geprüft wird, dass die Tabellenzeile fehlt.
        $this->get(route('doctors.index'))->assertDontSee('t.krause@klinikum-nord.test')->assertDontSee(route('doctors.edit', $colleague));
        $this->get(route('doctors.edit', $colleague))->assertNotFound();

        $this->post('/logout');
        $this->post('/login', ['username' => 't.krause', 'password' => 'password', 'department' => 'telemonitoring'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_deleted_doctor_stays_visible_in_history(): void
    {
        $admin = $this->staff();
        $colleague = $this->colleague();
        $patient = $this->patient(['doctor_id' => $admin->id]);
        app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124]);
        app(AlarmService::class)->acknowledge(Alarm::firstOrFail(), $colleague, 'Angerufen');

        $this->actingAs($colleague)->post(route('calls.store', $patient));
        $call = Call::firstOrFail();

        $this->actingAs($admin)->delete(route('doctors.destroy', $colleague), ['confirm' => '1']);

        $this->assertSame(CallStatus::Declined, $call->fresh()->status);
        $this->assertSame('Tobias Krause', Alarm::firstOrFail()->acknowledgedBy?->name);
        $this->get('/anrufe')->assertOk()->assertSee('Dr. T. Krause');
    }

    public function test_cannot_delete_own_account_or_last_doctor(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin)
            ->delete(route('doctors.destroy', $admin), ['confirm' => '1'])
            ->assertSessionHasErrors('doctor');

        $this->assertNotSoftDeleted($admin);
        $this->get(route('doctors.edit', $admin))->assertSee('Sie können Ihr eigenes Konto nicht löschen.');
    }

    public function test_replacement_must_be_another_active_doctor(): void
    {
        $admin = $this->staff();
        $colleague = $this->colleague();
        $this->patient(['doctor_id' => $colleague->id]);
        $patientUser = $this->patient()->user;

        $this->actingAs($admin);

        $this->delete(route('doctors.destroy', $colleague), ['confirm' => '1', 'replacement_id' => $colleague->id])
            ->assertSessionHasErrors('replacement_id');
        $this->delete(route('doctors.destroy', $colleague), ['confirm' => '1', 'replacement_id' => $patientUser->id])
            ->assertSessionHasErrors('replacement_id');

        $this->assertNotSoftDeleted($colleague);
    }

    public function test_deleted_doctor_is_not_offered_for_patients(): void
    {
        $admin = $this->staff();
        $colleague = $this->colleague();
        $this->actingAs($admin)->delete(route('doctors.destroy', $colleague), ['confirm' => '1']);

        $this->get(route('patients.create'))->assertOk()->assertDontSee('<option value="'.$colleague->id.'"', false);
    }

    public function test_patient_ids_are_not_resolved_as_doctors(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->staff())
            ->get(route('doctors.edit', $patient->user_id))
            ->assertNotFound();
    }

    public function test_patients_cannot_manage_doctors(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)->get(route('doctors.index'))->assertStatus(403);
        $this->actingAs($patient->user)->post(route('doctors.store'), $this->payload())->assertStatus(403);
    }
}
