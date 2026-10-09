<?php

namespace App\Notifications\Contracts;

use App\Models\User;
use App\Support\PushMessage;

/**
 * Benachrichtigung, die (auch) als Push-Benachrichtigung aufs Handy geht.
 */
interface SendsPush
{
    public function toWebPush(User $notifiable): PushMessage;
}
