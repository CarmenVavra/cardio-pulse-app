<?php

namespace App\Services;

use App\Models\Call;
use App\Models\CallSignal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Signalisierung der Videosprechstunde über die Datenbank (Polling statt WebSockets –
 * läuft so auch auf Webhosting). Der Arzt-Browser macht immer das Angebot, der
 * Patienten-Browser antwortet; so können nie beide gleichzeitig anbieten.
 */
class CallSignalService
{
    /** Höchstens so viele Nachrichten pro Abruf. */
    private const BATCH = 100;

    public function senderFor(User $user): string
    {
        return $user->isStaff() ? CallSignal::STAFF : CallSignal::PATIENT;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Call $call, User $user, string $type, array $payload): CallSignal
    {
        return CallSignal::create([
            'call_id' => $call->id,
            'sender' => $this->senderFor($user),
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    /**
     * Neue Nachrichten der Gegenseite seit $afterId.
     *
     * @return Collection<int, CallSignal>
     */
    public function receive(Call $call, User $user, int $afterId): Collection
    {
        $from = $this->senderFor($user) === CallSignal::STAFF ? CallSignal::PATIENT : CallSignal::STAFF;

        return CallSignal::query()
            ->where('call_id', $call->id)
            ->where('sender', $from)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();
    }

    /**
     * Nach dem Gespräch löschen – die Kandidaten enthalten IP-Adressen.
     */
    public function purge(Call $call): void
    {
        CallSignal::query()->where('call_id', $call->id)->delete();
    }

    /**
     * STUN-/TURN-Server für den Browser (RTCPeerConnection iceServers).
     *
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    public function iceServers(): array
    {
        $servers = [];

        $stun = array_values(array_filter(array_map('trim', explode(',', (string) config('cardiopulse.video.stun')))));
        if ($stun !== []) {
            $servers[] = ['urls' => $stun];
        }

        $turn = trim((string) config('cardiopulse.video.turn_url'));
        if ($turn !== '') {
            $servers[] = [
                'urls' => [$turn],
                'username' => (string) config('cardiopulse.video.turn_username'),
                'credential' => (string) config('cardiopulse.video.turn_credential'),
            ];
        }

        return $servers;
    }
}
