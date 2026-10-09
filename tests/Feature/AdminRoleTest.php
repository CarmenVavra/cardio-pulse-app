<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(string $username = 't.krause', string $name = 'Tobias Krause'): User
    {
        return User::factory()->staff()->create(['username' => $username, 'name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $doctor, array $overrides = []): array
    {
        return [
            'title' => $doctor->title,
            'name' => $doctor->name,
            'username' => $doctor->username,
            'email' => $doctor->email,
            'phone' => $doctor->phone,
            'available_until' => $doctor->available_until,
            ...$overrides,
        ];
    }

    public function test_doctors_without_admin_rights_cannot_manage_doctors(): void
    {
        $this->staff();
        $doctor = $this->doctor();

        $this->actingAs($doctor)
            ->get(route('board'))
            ->assertOk()
            ->assertDontSee(route('doctors.index'));

        $this->get(route('doctors.index'))->assertForbidden()->assertSee('Dafür fehlt Ihnen die Berechtigung.');
        $this->get(route('doctors.create'))->assertForbidden();
        $this->post(route('doctors.store'), [])->assertForbidden();
        $this->put(route('doctors.update', $doctor), $this->profile($doctor, ['is_admin' => '1']))->assertForbidden();
        $this->delete(route('doctors.destroy', $doctor), ['confirm' => '1'])->assertForbidden();

        $this->assertFalse($doctor->fresh()->is_admin);
    }

    public function test_admin_sees_doctor_menu_and_admin_badge(): void
    {
        $admin = $this->staff();
        $this->doctor();

        $this->actingAs($admin)
            ->get(route('board'))
            ->assertSee(route('doctors.index'));

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSeeInOrder(['Dr. Miriam Weber', 'Admin', 'Dr. Tobias Krause']);
    }

    public function test_admin_grants_and_revokes_admin_rights(): void
    {
        $admin = $this->staff();
        $doctor = $this->doctor();

        $this->actingAs($admin)
            ->put(route('doctors.update', $doctor), $this->profile($doctor, ['is_admin' => '1']))
            ->assertRedirect(route('doctors.index'));
        $this->assertTrue($doctor->fresh()->is_admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.updated', 'auditable_id' => $doctor->id]);

        $this->put(route('doctors.update', $doctor), $this->profile($doctor, ['is_admin' => '0']))
            ->assertRedirect(route('doctors.index'));
        $this->assertFalse($doctor->fresh()->is_admin);
    }

    public function test_admin_cannot_revoke_own_admin_rights(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin)
            ->get(route('doctors.edit', $admin))
            ->assertOk()
            ->assertSee('Ihre eigenen Admin-Rechte kann nur ein anderer Admin entziehen.');

        $this->put(route('doctors.update', $admin), $this->profile($admin, ['is_admin' => '0']))
            ->assertRedirect(route('doctors.index'));

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_new_doctor_is_admin_only_when_checked(): void
    {
        $this->actingAs($this->staff());
        $payload = [
            'title' => 'Dr.',
            'name' => 'Lena Hofer',
            'email' => 'l.hofer@klinikum-nord.test',
            'password' => 'sicher-123',
            'password_confirmation' => 'sicher-123',
            'pin' => '482915',
        ];

        $this->post(route('doctors.store'), [...$payload, 'username' => 'l.hofer'])->assertRedirect(route('doctors.index'));
        $this->post(route('doctors.store'), [...$payload, 'username' => 'a.berger', 'email' => 'a.berger@klinikum-nord.test', 'is_admin' => '1'])
            ->assertRedirect(route('doctors.index'));

        $this->assertFalse(User::query()->where('username', 'l.hofer')->sole()->is_admin);
        $this->assertTrue(User::query()->where('username', 'a.berger')->sole()->is_admin);
    }

    public function test_admin_rights_can_be_restored_on_the_command_line(): void
    {
        $doctor = $this->doctor();

        $this->artisan('cardiopulse:make-admin', ['username' => 'T.Krause'])
            ->expectsOutput('Dr. Tobias Krause ist Admin.')
            ->assertSuccessful();
        $this->assertTrue($doctor->fresh()->is_admin);

        $this->artisan('cardiopulse:make-admin', ['username' => 'unbekannt'])->assertFailed();
    }

    public function test_command_line_creates_further_doctors_without_admin_rights(): void
    {
        $this->staff();

        $this->artisan('cardiopulse:create-doctor')
            ->expectsQuestion('Titel (z. B. Dr., leer lassen für keinen)', 'Dr.')
            ->expectsQuestion('Vor- und Nachname', 'Lena Hofer')
            ->expectsQuestion('Benutzerkennung (z. B. m.weber)', 'l.hofer')
            ->expectsQuestion('E-Mail', 'l.hofer@klinikum-nord.test')
            ->expectsQuestion('Telefon (optional)', '')
            ->expectsQuestion('Passwort (mind. 8 Zeichen, Buchstaben und Ziffern)', 'sicher-2026')
            ->expectsQuestion('PIN für den Privacy-Lock (6 Ziffern)', '482915')
            ->expectsOutput('Dr. Lena Hofer wurde angelegt. Anmeldung mit „l.hofer“.')
            ->assertSuccessful();

        $this->assertFalse(User::query()->where('username', 'l.hofer')->sole()->is_admin);
    }
}
