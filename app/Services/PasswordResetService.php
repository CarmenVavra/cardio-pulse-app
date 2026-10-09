<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * „Passwort vergessen“: Link per E-Mail senden und Passwort damit neu setzen.
 *
 * Der Antworttext verrät nie, ob es zu einer E-Mail-Adresse ein Konto gibt.
 */
class PasswordResetService
{
    public function __construct(private readonly AccountService $accounts) {}

    /**
     * Link senden, falls es ein aktives Konto mit dieser E-Mail gibt. Laravel lässt
     * pro Konto höchstens eine Anforderung pro Minute zu (auth.passwords.users.throttle).
     */
    public function sendLink(string $email): void
    {
        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (TransportExceptionInterface $e) {
            // Mailserver nicht erreichbar oder falsch eingerichtet: protokollieren,
            // dem Besucher aber dieselbe Meldung zeigen wie immer.
            report($e);

            return;
        }

        if ($status === Password::RESET_LINK_SENT) {
            $user = User::query()->where('email', $email)->first();
            AuditLog::record('account.password_reset_requested', $user, [], $user);
        }
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return string Status des Password-Brokers, z. B. {@see Password::PASSWORD_RESET}
     */
    public function reset(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password) {
            $this->accounts->resetPassword($user, $password);
        });
    }
}
