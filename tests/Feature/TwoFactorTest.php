<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const RECOVERY = ['aaaaa-bbbbb', 'ccccc-ddddd'];

    private function google2fa(): Google2FA
    {
        return app(Google2FA::class);
    }

    /**
     * Code für einen Zeitschritt relativ zu jetzt (0 = aktueller, 1 = nächster).
     */
    private function code(string $secret, int $offset = 0): string
    {
        return $this->google2fa()->oathTotp($secret, $this->google2fa()->getTimestamp() + $offset);
    }

    private function withTwoFactor(User $user): string
    {
        $secret = $this->google2fa()->generateSecretKey(32);
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => self::RECOVERY,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    private function login(): void
    {
        $this->post('/login', ['username' => 'm.weber', 'password' => 'password', 'department' => 'telemonitoring', 'sound' => '1'])
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_doctor_sets_up_two_factor_with_the_app(): void
    {
        $doctor = $this->staff();
        $this->actingAs($doctor)->get(route('account.edit'))->assertOk()->assertSee('Einrichten');

        $page = $this->get(route('account.two-factor.create'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('QR-Code für die Authenticator-App');
        $secret = app(TwoFactorService::class)->pendingSecret($doctor);
        $page->assertSee(app(TwoFactorService::class)->formatSecret($secret));

        // Neu laden zeigt denselben Schlüssel – der gescannte QR-Code bleibt gültig.
        $this->get(route('account.two-factor.create'))->assertSee(app(TwoFactorService::class)->formatSecret($secret));

        $this->post(route('account.two-factor.store'), ['code' => '000000', 'current_password' => 'password'])
            ->assertSessionHasErrors('code', null, 'twoFactor');
        $this->post(route('account.two-factor.store'), ['code' => $this->code($secret), 'current_password' => 'falsch'])
            ->assertSessionHasErrors('current_password', null, 'twoFactor');
        $this->assertFalse($doctor->fresh()->hasTwoFactor());

        $this->post(route('account.two-factor.store'), ['code' => $this->code($secret), 'current_password' => 'password'])
            ->assertRedirect(route('account.edit'));

        $doctor->refresh();
        $this->assertTrue($doctor->hasTwoFactor());
        $this->assertSame($secret, $doctor->two_factor_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $doctor->id)->value('two_factor_secret'), 'Schlüssel ist verschlüsselt gespeichert.');
        $this->assertFalse(app(TwoFactorService::class)->hasPendingSecret($doctor));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.two_factor_enabled', 'user_id' => $doctor->id]);

        // Die Codes erscheinen genau einmal.
        $this->get(route('account.edit'))
            ->assertSee('Ihre Wiederherstellungscodes')
            ->assertSee($doctor->two_factor_recovery_codes[0])
            ->assertSee('Eingerichtet');
        $this->get(route('account.edit'))->assertDontSee('Ihre Wiederherstellungscodes');
    }

    /**
     * Regression: Die Live-Aktualisierung speichert die Session ständig neu. Ein dort abgelegter
     * Schlüssel ging verloren, die App erzeugte unbemerkt einen neuen und der gescannte Code passte nie.
     */
    public function test_setup_survives_an_overwritten_session(): void
    {
        $doctor = $this->staff();
        $this->actingAs($doctor)->get(route('account.two-factor.create'))->assertOk();
        $secret = app(TwoFactorService::class)->pendingSecret($doctor);

        $this->flushSession();
        $this->actingAs($doctor);

        $this->post(route('account.two-factor.store'), ['code' => $this->code($secret), 'current_password' => 'password'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasNoErrors('twoFactor');
        $this->assertTrue($doctor->fresh()->hasTwoFactor());
    }

    public function test_expired_setup_asks_for_a_new_scan(): void
    {
        $doctor = $this->staff();
        $this->actingAs($doctor)->get(route('account.two-factor.create'));
        $secret = app(TwoFactorService::class)->pendingSecret($doctor);

        $this->travel(TwoFactorService::SETUP_MINUTES + 1)->minutes();

        $this->post(route('account.two-factor.store'), ['code' => $this->code($secret), 'current_password' => 'password'])
            ->assertRedirect(route('account.two-factor.create'))
            ->assertSessionHasErrors(['code' => 'Der QR-Code ist abgelaufen. Bitte löschen Sie den Eintrag in der App und scannen Sie den neuen QR-Code.'], null, 'twoFactor');
        $this->assertFalse($doctor->fresh()->hasTwoFactor());
    }

    public function test_login_needs_the_code_after_the_password(): void
    {
        $doctor = $this->staff();
        $secret = $this->withTwoFactor($doctor);

        $this->login();
        $this->assertGuest();
        $this->get(route('board'))->assertRedirect(route('login'));

        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Bestätigungscode');
        $this->post(route('two-factor.challenge'), ['code' => '123456'])
            ->assertSessionHasErrors(['code' => 'Der Code ist nicht korrekt oder wurde bereits verwendet.']);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_failed', 'user_id' => $doctor->id]);

        $this->post(route('two-factor.challenge'), ['code' => $this->code($secret)])->assertRedirect(route('board'));
        $this->assertAuthenticatedAs($doctor);
        $this->assertSame('telemonitoring', session('department'));
        $this->assertTrue(session('sound_enabled'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $doctor->id]);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $doctor = $this->staff();
        $secret = $this->withTwoFactor($doctor);
        $code = $this->code($secret);

        $this->login();
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('board'));
        $this->post('/logout');

        $this->login();
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.challenge'), ['code' => $this->code($secret, 1)])->assertRedirect(route('board'));
        $this->assertAuthenticatedAs($doctor);
    }

    public function test_recovery_code_works_once(): void
    {
        $doctor = $this->staff();
        $this->withTwoFactor($doctor);

        $this->login();
        $this->post(route('two-factor.challenge'), ['code' => ' AAAAA-BBBBB '])
            ->assertRedirect(route('board'))
            ->assertSessionHas('status', 'Sie haben einen Wiederherstellungscode verwendet – noch 1 übrig. Neue Codes erzeugen Sie unter „Mein Konto“.');
        $this->assertSame(['ccccc-ddddd'], $doctor->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.recovery_code_used']);
        $this->post('/logout');

        $this->login();
        $this->post(route('two-factor.challenge'), ['code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_pending_login_expires(): void
    {
        $doctor = $this->staff();
        $secret = $this->withTwoFactor($doctor);
        $this->login();

        $this->travel(6)->minutes();

        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.challenge'), ['code' => $this->code($secret)])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => 'Die Anmeldung ist abgelaufen. Bitte melden Sie sich erneut an.']);
        $this->assertGuest();
    }

    public function test_wrong_codes_lock_the_account(): void
    {
        $doctor = $this->staff();
        $secret = $this->withTwoFactor($doctor);
        $this->login();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('two-factor.challenge'), ['code' => '000000']);
        }

        $this->post(route('two-factor.challenge'), ['code' => $this->code($secret)])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_doctor_renews_recovery_codes_and_disables_with_password(): void
    {
        $doctor = $this->staff();
        $this->withTwoFactor($doctor);
        $this->actingAs($doctor);

        $this->post(route('account.two-factor.recovery-codes'), ['two_factor_password' => 'falsch'])
            ->assertSessionHasErrors('two_factor_password', null, 'twoFactorManage');
        $this->post(route('account.two-factor.recovery-codes'), ['two_factor_password' => 'password'])
            ->assertRedirect(route('account.edit'));
        $this->get(route('account.edit'))->assertSee('Ihre Wiederherstellungscodes');
        $this->assertCount(8, $doctor->fresh()->two_factor_recovery_codes);
        $this->assertNotContains('aaaaa-bbbbb', $doctor->fresh()->two_factor_recovery_codes);

        $this->post(route('account.two-factor.destroy'), ['two_factor_password' => 'password'])->assertRedirect(route('account.edit'));
        $this->assertFalse($doctor->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.two_factor_disabled', 'user_id' => $doctor->id]);
    }

    public function test_admin_resets_two_factor_of_another_doctor(): void
    {
        $admin = $this->staff();
        $colleague = User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
        $this->withTwoFactor($colleague);

        $this->actingAs($admin)
            ->get(route('doctors.edit', $colleague))
            ->assertSee('Zwei-Faktor-Anmeldung zurücksetzen');

        $this->delete(route('doctors.two-factor.destroy', $colleague))->assertRedirect(route('doctors.edit', $colleague));
        $this->assertFalse($colleague->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.two_factor_reset', 'user_id' => $admin->id, 'auditable_id' => $colleague->id]);

        $this->withTwoFactor($admin);
        $this->delete(route('doctors.two-factor.destroy', $admin))->assertSessionHasErrors('two_factor', null, 'twoFactorReset');
        $this->assertTrue($admin->fresh()->hasTwoFactor());

        $this->actingAs($colleague)->delete(route('doctors.two-factor.destroy', $admin))->assertForbidden();
    }

    public function test_two_factor_can_be_required_for_all_doctors(): void
    {
        config(['cardiopulse.require_two_factor' => true]);
        $doctor = $this->staff();
        $this->actingAs($doctor);

        $this->get(route('board'))->assertRedirect(route('account.two-factor.create'));
        $this->get(route('patients.index'))->assertRedirect(route('account.two-factor.create'));
        $this->getJson(route('board.live'))->assertForbidden();
        $this->get(route('account.two-factor.create'))->assertOk()->assertDontSee('Zurück zu Mein Konto');

        // Gesperrter Bildschirm bleibt erreichbar – keine Umleitungsschleife zwischen Lock und Einrichtung.
        $this->withSession(['screen_locked' => true])->get(route('board'))->assertOk();
        $this->withSession(['screen_locked' => false]);

        $this->withTwoFactor($doctor);
        $this->get(route('board'))->assertOk();
        $this->post(route('account.two-factor.destroy'), ['two_factor_password' => 'password'])
            ->assertSessionHasErrors('two_factor_password', null, 'twoFactorManage');
        $this->assertTrue($doctor->fresh()->hasTwoFactor());
    }

    public function test_two_factor_can_be_reset_on_the_command_line(): void
    {
        $doctor = $this->staff();
        $this->withTwoFactor($doctor);

        $this->artisan('cardiopulse:reset-two-factor', ['username' => 'M.Weber'])
            ->expectsOutput('Die Zwei-Faktor-Anmeldung von Dr. Miriam Weber wurde zurückgesetzt.')
            ->assertSuccessful();

        $this->assertFalse($doctor->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.two_factor_reset', 'user_id' => null]);
        $this->artisan('cardiopulse:reset-two-factor', ['username' => 'unbekannt'])->assertFailed();
    }

    public function test_doctor_list_shows_two_factor_status(): void
    {
        $admin = $this->staff();
        $this->withTwoFactor($admin);

        $this->actingAs($admin)->get(route('doctors.index'))->assertSeeInOrder(['2FA', 'Dr. Miriam Weber', 'Ja']);
    }
}
