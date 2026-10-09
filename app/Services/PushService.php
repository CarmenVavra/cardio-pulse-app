<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushMessage;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Push-Benachrichtigungen (Web Push mit VAPID). Die Nachricht wird für jedes Gerät
 * verschlüsselt; der Push-Dienst (Google, Apple, Mozilla) stellt sie nur zu.
 *
 * Versand sofort (kein Queue-Worker auf dem Webhosting), mit kurzer Zeitgrenze – ein
 * nicht erreichbarer Push-Dienst darf Anruf, Nachricht oder Termin nie aufhalten.
 */
class PushService
{
    private const TIMEOUT_SECONDS = 5;

    public function enabled(): bool
    {
        return $this->publicKey() !== null && (string) config('cardiopulse.push.private_key') !== '';
    }

    public function publicKey(): ?string
    {
        $key = (string) config('cardiopulse.push.public_key');

        return $key !== '' ? $key : null;
    }

    /**
     * Nur Adressen der echten Push-Dienste der Browser zulassen.
     */
    public function isAllowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        foreach ((array) config('cardiopulse.push.allowed_hosts') as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public function subscribe(User $user, string $endpoint, string $publicKey, string $authToken, string $encoding, ?string $userAgent): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $encoding,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            ],
        );
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();
    }

    /**
     * An alle Geräte des Benutzers senden. Abgelaufene Abos werden gelöscht.
     *
     * @return int Anzahl zugestellter Benachrichtigungen
     */
    public function send(User $user, PushMessage $message): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = $this->client();
            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding,
                    ]),
                    $message->payload(),
                    ['TTL' => $message->ttl, 'urgency' => $message->urgency, 'topic' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $message->tag) ?? '', 0, 32) ?: null],
                );
            }

            $delivered = 0;
            foreach ($webPush->flush() as $report) {
                $subscription = $subscriptions->firstWhere('endpoint', $report->getEndpoint());

                if ($report->isSuccess()) {
                    $subscription?->forceFill(['last_used_at' => now()])->save();
                    $delivered++;
                } elseif ($report->isSubscriptionExpired()) {
                    // Gerät hat die Benachrichtigungen abbestellt oder die App gelöscht.
                    $subscription?->delete();
                }
            }

            return $delivered;
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => (string) config('cardiopulse.push.subject'),
                'publicKey' => (string) config('cardiopulse.push.public_key'),
                'privateKey' => (string) config('cardiopulse.push.private_key'),
            ],
        ], [], self::TIMEOUT_SECONDS);
    }
}
