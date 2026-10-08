<?php

namespace Tests\Feature;

use App\Services\LoginThrottle;
use App\Services\PrivacyLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:m.weber|127.0.0.1');
    }

    public function test_staff_account_is_locked_after_repeated_failed_logins(): void
    {
        $staff = $this->staff();

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['username' => 'm.weber', 'password' => 'falsch', 'department' => 'telemonitoring']);
        }

        // Auch mit richtigem Passwort gesperrt, bis die Sperrzeit abgelaufen ist.
        $this->post('/login', ['username' => 'm.weber', 'password' => 'password', 'department' => 'telemonitoring'])
            ->assertSessionHasErrors(['username' => 'Zu viele Fehlversuche. Das Konto ist für 5 Minuten gesperrt.']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.locked']);

        $this->travel(LoginThrottle::DECAY_SECONDS + 1)->seconds();
        $this->post('/login', ['username' => 'm.weber', 'password' => 'password', 'department' => 'telemonitoring'])
            ->assertRedirect(route('board'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $this->staff();

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS - 1; $i++) {
            $this->post('/login', ['username' => 'm.weber', 'password' => 'falsch', 'department' => 'telemonitoring']);
        }
        $this->post('/login', ['username' => 'm.weber', 'password' => 'password', 'department' => 'telemonitoring']);
        $this->post('/logout');

        $this->post('/login', ['username' => 'm.weber', 'password' => 'falsch', 'department' => 'telemonitoring'])
            ->assertSessionHasErrors(['username' => 'Benutzerkennung oder Passwort ist falsch.']);
    }

    public function test_patient_login_is_locked_after_repeated_failures(): void
    {
        $patient = $this->patient();
        $email = $patient->user->email;
        RateLimiter::clear('login:'.$email.'|127.0.0.1');

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS; $i++) {
            $this->post('/app/login', ['email' => $email, 'password' => 'falsch']);
        }

        $this->post('/app/login', ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_pins_show_remaining_attempts_and_finally_log_out(): void
    {
        $staff = $this->staff();
        RateLimiter::clear('pin:'.$staff->id);

        $this->actingAs($staff)->withSession(['screen_locked' => true]);

        $this->post('/sperre/aufheben', ['pin' => '000000'])
            ->assertSessionHasErrors(['pin' => 'PIN ist nicht korrekt. Noch 4 Versuche, danach werden Sie abgemeldet.']);

        for ($i = 2; $i < PrivacyLockService::MAX_PIN_ATTEMPTS; $i++) {
            $this->post('/sperre/aufheben', ['pin' => '000000']);
        }

        $this->post('/sperre/aufheben', ['pin' => '000000'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'screen.unlock_locked_out', 'user_id' => $staff->id]);
    }

    public function test_correct_pin_resets_failed_attempts(): void
    {
        $staff = $this->staff();
        RateLimiter::clear('pin:'.$staff->id);
        $this->actingAs($staff)->withSession(['screen_locked' => true]);

        $this->post('/sperre/aufheben', ['pin' => '000000']);
        $this->post('/sperre/aufheben', ['pin' => '123456'])->assertRedirect(route('board'));

        $this->assertSame(PrivacyLockService::MAX_PIN_ATTEMPTS, app(PrivacyLockService::class)->remainingAttempts($staff));
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
