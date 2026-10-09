<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_page_is_rendered(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Anmelden')
            ->assertSee('Signalton für diesen Bildschirm aktivieren')
            ->assertSee('Haftungsausschluss');
    }

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_staff_can_log_in_with_department_and_sound(): void
    {
        $this->staff();

        $this->post('/login', [
            'username' => 'm.weber',
            'password' => 'password',
            'department' => 'telemonitoring',
            'sound' => '1',
        ])->assertRedirect(route('board'))
            ->assertSessionHas('department', 'telemonitoring')
            ->assertSessionHas('sound_enabled', true);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login']);
    }

    public function test_staff_login_fails_with_wrong_password(): void
    {
        $this->staff();

        $this->from('/login')->post('/login', [
            'username' => 'm.weber',
            'password' => 'falsch',
            'department' => 'telemonitoring',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_department_must_be_valid(): void
    {
        $this->staff();

        $this->post('/login', [
            'username' => 'm.weber',
            'password' => 'password',
            'department' => 'unbekannt',
        ])->assertSessionHasErrors('department');
    }

    public function test_patient_cannot_use_staff_login(): void
    {
        User::factory()->create(['username' => 'patient', 'password' => 'password']);

        $this->post('/login', [
            'username' => 'patient',
            'password' => 'password',
            'department' => 'telemonitoring',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_patient_email_on_staff_login_points_to_the_patient_app(): void
    {
        $patient = $this->patient();

        $this->from('/login')->post('/login', [
            'username' => $patient->user->email,
            'password' => 'password',
            'department' => 'telemonitoring',
        ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'Das ist die Anmeldung für Ärzte – hier gilt die Benutzerkennung (z. B. m.weber), nicht die E-Mail-Adresse.'])
            ->assertSessionHas('patient_hint', true);

        $this->assertGuest();
        // Kein Anmeldeversuch für Ärzte: weder Fehlversuch im Protokoll noch Sperre.
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.failed']);

        $this->get('/login')
            ->assertSee('Patientin oder Patient?')
            ->assertSee('Zur Patienten-App')
            ->assertDontSee($patient->user->email); // E-Mail-Adresse wird nicht wieder angezeigt
    }

    public function test_patient_login_identifies_the_email_as_username_for_password_managers(): void
    {
        $this->get('/app/login')->assertSee('id="email" name="email" type="email" value="" autocomplete="username"', false);
        $this->get('/login')->assertSee('autocomplete="username"', false);
    }

    public function test_patient_can_log_in_to_app(): void
    {
        $patient = $this->patient();

        $this->post('/app/login', [
            'email' => $patient->user->email,
            'password' => 'password',
        ])->assertRedirect(route('patient.home'));

        $this->assertAuthenticatedAs($patient->user);
    }

    public function test_guests_are_redirected_to_matching_login(): void
    {
        $this->get('/ueberwachung')->assertRedirect('/login');
        $this->get('/app')->assertRedirect('/app/login');
    }

    public function test_roles_are_separated(): void
    {
        $patient = $this->patient();
        $staff = $this->staff();

        $this->actingAs($patient->user)->get('/ueberwachung')->assertStatus(403);
        $this->actingAs($staff)->get('/app')->assertStatus(403);
    }

    public function test_logout_redirects_to_role_login(): void
    {
        $this->actingAs($this->staff())->post('/logout')->assertRedirect('/login');
        $this->actingAs($this->patient()->user)->post('/logout')->assertRedirect('/app/login');
    }
}
