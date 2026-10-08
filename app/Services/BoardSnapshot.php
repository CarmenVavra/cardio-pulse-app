<?php

namespace App\Services;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Models\Alarm;
use App\Models\Call;
use App\Support\BoardRow;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Momentaufnahme des Überwachungsscreens (initiales Rendern und Live-Aktualisierung).
 */
class BoardSnapshot
{
    public function __construct(
        private readonly MonitoringBoard $board,
        private readonly AlarmService $alarms,
    ) {}

    /**
     * @return array{rows: Collection<int, BoardRow>, counts: array<string, int>, uploadsToday: int, patientsTotal: int}
     */
    public function board(): array
    {
        $rows = $this->board->rows();

        return [
            'rows' => $rows,
            'counts' => $this->board->counts($rows),
            'uploadsToday' => $this->board->uploadsToday(),
            'patientsTotal' => $rows->count(),
        ];
    }

    /**
     * @return array{openAlarms: EloquentCollection<int, Alarm>, acknowledged: Alarm|null}
     */
    public function alarms(): array
    {
        $open = $this->alarms->open();

        return [
            'openAlarms' => $open,
            'acknowledged' => $open->isEmpty() ? $this->alarms->recentlyAcknowledged() : null,
        ];
    }

    /**
     * Ältester klingelnder Anruf eines Patienten an die Klinik.
     */
    public function incomingCall(): ?Call
    {
        return Call::query()
            ->where('direction', CallDirection::ToClinic)
            ->where('status', CallStatus::Ringing)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereHas('patient')
            ->with('patient')
            ->oldest()
            ->first();
    }
}
