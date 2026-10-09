<?php

namespace App\Support;

/**
 * Inhalt einer Push-Benachrichtigung. Sie erscheint auch auf dem Sperrbildschirm –
 * deshalb nie Messwerte, Diagnosen oder Nachrichtentexte hineinschreiben.
 */
final class PushMessage
{
    /**
     * @param  string  $tag  gleiche Kennung ersetzt eine ältere Benachrichtigung (z. B. pro Anruf)
     * @param  int  $ttl  Sekunden, die der Push-Dienst zustellen darf (Anruf: kurz)
     * @param  string  $urgency  very-low | low | normal | high
     * @param  bool  $requireInteraction  bleibt sichtbar, bis der Patient reagiert
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $tag,
        public readonly int $ttl = 3600,
        public readonly string $urgency = 'normal',
        public readonly bool $requireInteraction = false,
    ) {}

    /**
     * Was der Service Worker auf dem Gerät bekommt.
     */
    public function payload(): string
    {
        return (string) json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'requireInteraction' => $this->requireInteraction,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
