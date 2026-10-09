<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-Mail an den Patienten: neuer oder abgesagter Termin für eine Videosprechstunde.
 *
 * Sofort gesendet (kein Queue-Worker auf dem Webhosting). Keine Diagnosen oder
 * Messwerte in der E-Mail – nur Zeitpunkt, Arzt und der Hinweis auf die App.
 */
class AppointmentNotification extends Notification
{
    public const SCHEDULED = 'scheduled';

    public const CANCELLED = 'cancelled';

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $kind,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $doctor = $this->appointment->doctor?->displayName() ?? 'Ihr Behandlungsteam';
        $app = config('app.name');

        $mail = (new MailMessage)->greeting('Guten Tag '.$notifiable->name.',');

        if ($this->kind === self::CANCELLED) {
            return $mail
                ->subject($app.': Termin abgesagt')
                ->line('Ihr Termin für die Videosprechstunde wurde abgesagt:')
                ->line($this->appointment->when().' mit '.$doctor)
                ->line('Bei Fragen schreiben Sie uns eine Nachricht in der App.')
                ->action('Zur App', route('patient.doctor'))
                ->salutation('Ihr '.$app.'-Team');
        }

        return $mail
            ->subject($app.': Termin für Ihre Videosprechstunde')
            ->line('Für Sie wurde eine Videosprechstunde vereinbart:')
            ->line($this->appointment->when().' mit '.$doctor)
            ->line('Ihr Arzt ruft Sie zur Terminzeit in der App per Video an. Halten Sie bitte Ihr Handy bereit, öffnen Sie vorher die App und erlauben Sie Kamera und Mikrofon.')
            ->line('Falls Sie nicht können, sagen Sie den Termin bitte in der App ab.')
            ->action('Termin in der App ansehen', route('patient.doctor'))
            ->salutation('Ihr '.$app.'-Team');
    }
}
