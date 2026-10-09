<?php

namespace App\Notifications;

use App\Models\Call;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\SendsPush;
use App\Support\PushMessage;
use Illuminate\Notifications\Notification;

/**
 * Push aufs Handy: Der Arzt ruft den Patienten an – auch wenn die App geschlossen ist.
 */
class IncomingCallNotification extends Notification implements SendsPush
{
    public function __construct(public readonly Call $call) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return WebPushChannel::availableFor($notifiable) ? [WebPushChannel::class] : [];
    }

    public function toWebPush(User $notifiable): PushMessage
    {
        $caller = $this->call->user?->displayName() ?? 'Ihr Behandlungsteam';

        return new PushMessage(
            title: $caller.' ruft Sie an',
            body: 'Tippen Sie hier, um das Gespräch in CardioPulse anzunehmen.',
            url: route('patient.calls.show', $this->call),
            tag: 'call-'.$this->call->id,
            // Ein Anruf ist nach einer Minute vorbei – später nicht mehr zustellen.
            ttl: 60,
            urgency: 'high',
            requireInteraction: true,
        );
    }
}
