<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-Mail mit Link zum Zurücksetzen des Passworts.
 *
 * Wird sofort gesendet (nicht über die Queue) – auf dem Webhosting läuft kein Queue-Worker.
 * Die E-Mail-Adresse steht bewusst nicht im Link, damit sie nicht in Server-Logs landet;
 * sie wird im Formular erneut eingegeben.
 */
class ResetPasswordNotification extends ResetPassword
{
    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(config('app.name').': Passwort zurücksetzen')
            ->greeting('Guten Tag '.$notifiable->displayName().',')
            ->line('für Ihr '.config('app.name').'-Konto wurde ein neues Passwort angefordert.')
            ->action('Neues Passwort festlegen', $this->resetUrl($notifiable))
            ->line('Der Link ist '.$minutes.' Minuten gültig und funktioniert nur einmal. Bitte geben Sie im Formular Ihre E-Mail-Adresse ein.')
            ->line('Falls Sie kein neues Passwort angefordert haben, können Sie diese E-Mail ignorieren – Ihr Passwort bleibt unverändert.')
            ->salutation('Ihr '.config('app.name').'-Team');
    }

    /**
     * Patienten landen in der Smartphone-Ansicht, Ärzte in der Krankenhaus-Ansicht.
     *
     * @param  User  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return route($notifiable->isPatient() ? 'patient.password.reset' : 'password.reset', ['token' => $this->token]);
    }
}
