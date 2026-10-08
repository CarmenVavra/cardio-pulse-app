<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_doctor_can_be_created_on_the_command_line(): void
    {
        $this->artisan('cardiopulse:create-doctor')
            ->expectsQuestion('Titel (z. B. Dr., leer lassen für keinen)', 'Dr.')
            ->expectsQuestion('Vor- und Nachname', 'Miriam Weber')
            ->expectsQuestion('Benutzerkennung (z. B. m.weber)', 'M.Weber')
            ->expectsQuestion('E-Mail', 'm.weber@klinikum-nord.test')
            ->expectsQuestion('Telefon (optional)', '')
            ->expectsQuestion('Passwort (mind. 8 Zeichen, Buchstaben und Ziffern)', 'sicher-2026')
            ->expectsQuestion('PIN für den Privacy-Lock (6 Ziffern)', '482915')
            ->expectsOutput('Dr. Miriam Weber wurde angelegt. Anmeldung mit „m.weber“.')
            ->assertSuccessful();

        $doctor = User::query()->where('username', 'm.weber')->sole();
        $this->assertSame(UserRole::Staff, $doctor->role);
        $this->assertTrue(Hash::check('482915', $doctor->pin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.created', 'user_id' => null]);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->artisan('cardiopulse:create-doctor')
            ->expectsQuestion('Titel (z. B. Dr., leer lassen für keinen)', '')
            ->expectsQuestion('Vor- und Nachname', 'Test')
            ->expectsQuestion('Benutzerkennung (z. B. m.weber)', 'x')
            ->expectsQuestion('E-Mail', 'keine-mail')
            ->expectsQuestion('Telefon (optional)', '')
            ->expectsQuestion('Passwort (mind. 8 Zeichen, Buchstaben und Ziffern)', 'kurz')
            ->expectsQuestion('PIN für den Privacy-Lock (6 Ziffern)', '12')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
