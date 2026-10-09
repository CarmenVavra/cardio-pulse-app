<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\SendsPush;
use App\Support\PushMessage;
use Illuminate\Notifications\Notification;

/**
 * Push aufs Handy: neue Nachricht vom Behandlungsteam. Der Text selbst steht nur in
 * der App – auf dem Sperrbildschirm soll niemand mitlesen können.
 */
class ClinicMessageNotification extends Notification implements SendsPush
{
    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return WebPushChannel::availableFor($notifiable) ? [WebPushChannel::class] : [];
    }

    public function toWebPush(User $notifiable): PushMessage
    {
        return new PushMessage(
            title: 'Neue Nachricht von Ihrem Behandlungsteam',
            body: 'Öffnen Sie CardioPulse, um sie zu lesen.',
            url: route('patient.doctor').'#nachrichten',
            tag: 'messages',
            ttl: 86400,
        );
    }
}
