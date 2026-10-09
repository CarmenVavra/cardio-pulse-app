<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const SENT = 'Falls es ein Konto mit dieser E-Mail-Adresse gibt, haben wir Ihnen einen Link zum Zurücksetzen gesendet.';

    private function requestToken(User $user, string $route = 'password.email'): string
    {
        Notification::fake();

        $this->post(route($route), ['email' => $user->email])->assertSessionHas('status', self::SENT);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return (string) $token;
    }

    public function test_login_pages_link_to_password_reset(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'));
        $this->get(route('patient.login'))->assertSee(route('patient.password.request'));

        $this->get(route('password.request'))->assertOk()->assertSee('Passwort vergessen')->assertSee('PIN für den Privacy-Lock bleibt unverändert');
        $this->get(route('patient.password.request'))->assertOk()->assertSee('Passwort vergessen');
    }

    public function test_doctor_gets_link_to_the_hospital_view(): void
    {
        $doctor = $this->staff();
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $doctor->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::SENT);

        Notification::assertSentTo($doctor, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($doctor) {
            $mail = $notification->toMail($doctor);

            return $mail->actionUrl === route('password.reset', $notification->token)
                && $mail->subject === 'CardioPulse: Passwort zurücksetzen'
                && ! str_contains($mail->actionUrl, 'email');
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_reset_requested', 'user_id' => $doctor->id]);
    }

    public function test_patient_gets_link_to_the_app_view(): void
    {
        $user = $this->patient()->user;
        Notification::fake();

        $this->post(route('patient.password.email'), ['email' => $user->email])->assertSessionHas('status', self::SENT);

        Notification::assertSentTo($user, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification) => $notification->toMail($user)->actionUrl === route('patient.password.reset', $notification->token));
    }

    public function test_unknown_and_deleted_accounts_get_the_same_answer_without_mail(): void
    {
        $deleted = User::factory()->staff()->create();
        $deleted->delete();
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'niemand@example.test'])->assertSessionHas('status', self::SENT);
        $this->post(route('password.email'), ['email' => $deleted->email])->assertSessionHas('status', self::SENT);

        Notification::assertNothingSent();
    }

    public function test_mail_is_german(): void
    {
        $doctor = $this->staff();

        $html = (string) (new ResetPasswordNotification('token-123'))->toMail($doctor)->render();

        $this->assertStringContainsString('Guten Tag Dr. Miriam Weber,', $html);
        $this->assertStringContainsString('Neues Passwort festlegen', $html);
        $this->assertStringContainsString('60 Minuten', $html);
        $this->assertStringContainsString('Falls die Schaltfläche „Neues Passwort festlegen“ nicht funktioniert', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }

    public function test_doctor_sets_new_password_and_all_sessions_end(): void
    {
        config(['session.driver' => 'database']);
        $doctor = $this->staff();
        DB::table('sessions')->insert(['id' => 'alte-sitzung', 'user_id' => $doctor->id, 'payload' => '', 'last_activity' => time()]);
        $rememberToken = $doctor->remember_token;
        $token = $this->requestToken($doctor);

        $this->get(route('password.reset', $token))->assertOk()->assertSee('Neues Passwort festlegen')->assertDontSee($doctor->email);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $doctor->email,
            'password' => 'neu-und-sicher-7',
            'password_confirmation' => 'neu-und-sicher-7',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Ihr Passwort wurde geändert. Bitte melden Sie sich mit dem neuen Passwort an.');

        $doctor->refresh();
        $this->assertTrue(Hash::check('neu-und-sicher-7', $doctor->password));
        $this->assertNotSame($rememberToken, $doctor->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'alte-sitzung']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_reset', 'user_id' => $doctor->id]);

        $this->get(route('login'))->assertSee('Ihr Passwort wurde geändert.');
        $this->post('/login', ['username' => 'm.weber', 'password' => 'neu-und-sicher-7', 'department' => 'telemonitoring'])
            ->assertRedirect(route('board'));
    }

    public function test_patient_returns_to_the_app_login(): void
    {
        $user = $this->patient()->user;
        $token = $this->requestToken($user, 'patient.password.email');

        $this->post(route('patient.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'neu-und-sicher-7',
            'password_confirmation' => 'neu-und-sicher-7',
        ])->assertRedirect(route('patient.login'));

        $this->assertTrue(Hash::check('neu-und-sicher-7', $user->fresh()->password));
    }

    public function test_invalid_used_or_foreign_tokens_are_rejected(): void
    {
        $doctor = $this->staff();
        $other = User::factory()->staff()->create();
        $token = $this->requestToken($doctor);
        $payload = ['password' => 'neu-und-sicher-7', 'password_confirmation' => 'neu-und-sicher-7'];
        $invalid = 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.';

        $this->post(route('password.update'), [...$payload, 'token' => 'falsch', 'email' => $doctor->email])
            ->assertSessionHasErrors(['email' => $invalid]);
        $this->post(route('password.update'), [...$payload, 'token' => $token, 'email' => $other->email])
            ->assertSessionHasErrors(['email' => $invalid]);
        $this->assertFalse(Hash::check('neu-und-sicher-7', $doctor->fresh()->password));

        $this->post(route('password.update'), [...$payload, 'token' => $token, 'email' => $doctor->email])->assertRedirect(route('login'));
        $this->post(route('password.update'), [...$payload, 'token' => $token, 'email' => $doctor->email, 'password' => 'noch-ein-neues-8', 'password_confirmation' => 'noch-ein-neues-8'])
            ->assertSessionHasErrors(['email' => $invalid]);
    }

    public function test_new_password_must_follow_the_policy(): void
    {
        $doctor = $this->staff();
        $token = $this->requestToken($doctor);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $doctor->email,
            'password' => 'kurz',
            'password_confirmation' => 'anders',
        ])->assertSessionHasErrors('password');
    }

    public function test_unreachable_mail_server_shows_the_same_answer(): void
    {
        $doctor = $this->staff();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);

        $this->post(route('password.email'), ['email' => $doctor->email])
            ->assertSessionHas('status', self::SENT);
    }
}
