<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_account_page_is_linked_in_header(): void
    {
        $this->actingAs($this->staff())
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Mein Konto')
            ->assertSee('Passwort ändern')
            ->assertSee('PIN für den Privacy-Lock ändern')
            ->assertSee('href="'.route('account.edit').'"', false);
    }

    public function test_staff_changes_password_and_other_sessions_are_ended(): void
    {
        config(['session.driver' => 'database']);
        $staff = $this->staff();
        DB::table('sessions')->insert([
            'id' => 'andere-sitzung', 'user_id' => $staff->id, 'ip_address' => '10.0.0.2',
            'user_agent' => 'Stationsrechner', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($staff)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'neues-passwort-42',
                'password_confirmation' => 'neues-passwort-42',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('neues-passwort-42', $staff->fresh()?->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'andere-sitzung']);
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed', 'user_id' => $staff->id]);
    }

    public function test_password_change_requires_correct_current_password_and_strong_new_password(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->put(route('account.password'), [
                'current_password' => 'falsch',
                'password' => 'kurz',
                'password_confirmation' => 'anders',
            ])
            ->assertSessionHasErrorsIn('password', ['current_password', 'password']);

        $this->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'nurbuchstaben',
            'password_confirmation' => 'nurbuchstaben',
        ])->assertSessionHasErrorsIn('password', ['password']);

        $this->assertTrue(Hash::check('password', $staff->fresh()?->password));
    }

    public function test_validation_messages_are_german(): void
    {
        $this->actingAs($this->staff())
            ->put(route('account.password'), ['current_password' => 'password', 'password' => '', 'password_confirmation' => ''])
            ->assertSessionHasErrorsIn('password', ['password' => 'Bitte füllen Sie das Feld „neues Passwort“ aus.']);

        $this->get(route('account.edit'))->assertSee('Bitte füllen Sie das Feld „neues Passwort“ aus.');
    }

    public function test_staff_changes_pin(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->put(route('account.pin'), ['pin_current_password' => 'password', 'pin' => '482915', 'pin_confirmation' => '482915'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status', 'Ihre PIN wurde geändert.');

        $this->assertTrue(Hash::check('482915', $staff->fresh()?->pin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.pin_changed']);
    }

    public function test_pin_change_rejects_wrong_password_and_trivial_pin(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->put(route('account.pin'), ['pin_current_password' => 'falsch', 'pin' => '111111', 'pin_confirmation' => '111111'])
            ->assertSessionHasErrorsIn('pin', ['pin_current_password', 'pin']);

        $this->assertTrue(Hash::check('123456', $staff->fresh()?->pin));
    }

    public function test_account_page_is_not_available_while_screen_is_locked(): void
    {
        $this->actingAs($this->staff())
            ->withSession(['screen_locked' => true])
            ->get(route('account.edit'))
            ->assertRedirect(route('board'));
    }

    public function test_patient_changes_password(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)
            ->get('/app')
            ->assertSee('Mein Konto · Passwort ändern');

        $this->get(route('patient.account.edit'))
            ->assertOk()
            ->assertSee($patient->user->email);

        $this->put(route('patient.account.password'), [
            'current_password' => 'password',
            'password' => 'blutdruck2026',
            'password_confirmation' => 'blutdruck2026',
        ])->assertRedirect(route('patient.account.edit'));

        $this->assertTrue(Hash::check('blutdruck2026', $patient->user->fresh()?->password));
    }

    public function test_patient_cannot_change_pin(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)
            ->put(route('account.pin'), ['pin_current_password' => 'password', 'pin' => '482915', 'pin_confirmation' => '482915'])
            ->assertStatus(403);
    }
}
