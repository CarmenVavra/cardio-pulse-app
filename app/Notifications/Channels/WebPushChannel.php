<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Contracts\SendsPush;
use App\Services\PushService;
use Illuminate\Notifications\Notification;

/**
 * Laravel-Benachrichtigungskanal für Web Push (Patienten-App auf dem Handy).
 */
class WebPushChannel
{
    public function __construct(private readonly PushService $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof SendsPush) {
            return;
        }

        $this->push->send($notifiable, $notification->toWebPush($notifiable));
    }

    /**
     * Kanal nur verwenden, wenn Push eingerichtet ist und der Benutzer ein Gerät angemeldet hat.
     */
    public static function availableFor(object $notifiable): bool
    {
        return $notifiable instanceof User
            && app(PushService::class)->enabled()
            && $notifiable->pushSubscriptions()->exists();
    }
}
