<?php

namespace App\Support;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use App\Models\Patient;
use Illuminate\Support\Carbon;

/**
 * Eine Zeile des Überwachungsscreens (ein Patient zu Hause).
 */
final class BoardRow
{
    public function __construct(
        public readonly Patient $patient,
        public readonly ?Measurement $measurement,
        public readonly ?BloodPressureStatus $status,
        public readonly ?Carbon $uploadedAt,
        public readonly string $uploadKind,
        public readonly ?string $sparkline,
        public readonly bool $hasOpenAlarm,
        public readonly int $unreadMessages = 0,
    ) {}

    /**
     * Eindeutiger Schlüssel des letzten Uploads – zum Hervorheben neuer Zeilen.
     */
    public function uploadKey(): string
    {
        return $this->patient->id.'-'.($this->uploadedAt?->getTimestamp() ?? 0);
    }

    public function sortKey(): string
    {
        $order = $this->status?->sortOrder() ?? 9;

        return $order.'-'.str_pad((string) (PHP_INT_MAX - ($this->uploadedAt?->getTimestamp() ?? 0)), 20, '0', STR_PAD_LEFT);
    }
}
