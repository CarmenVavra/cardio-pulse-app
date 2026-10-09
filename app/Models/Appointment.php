<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Termin für eine Videosprechstunde.
 *
 * @property int $patient_id
 * @property int|null $user_id
 * @property int|null $call_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 * @property AppointmentStatus $status
 * @property Carbon|null $cancelled_at
 * @property bool $cancelled_by_patient
 * @property Carbon|null $reminded_at
 * @property Carbon $created_at
 */
class Appointment extends Model
{
    /** So lange vor Beginn lässt sich die Videosprechstunde schon starten. */
    public const EARLY_START_MINUTES = 10;

    /** So lange nach dem geplanten Ende geht es noch. */
    public const LATE_START_MINUTES = 30;

    public const DURATIONS = [10, 15, 20, 30, 45, 60];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'user_id',
        'call_id',
        'starts_at',
        'ends_at',
        'reason',
        'status',
        'cancelled_at',
        'cancelled_by_patient',
        'reminded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'cancelled_at' => 'datetime',
            'cancelled_by_patient' => 'boolean',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * Behandelnder Arzt.
     *
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Call, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Geplante Termine, die noch nicht vorbei sind (inkl. der Nachlaufzeit zum Starten).
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Booked)
            ->where('ends_at', '>=', now()->subMinutes(self::LATE_START_MINUTES));
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Videosprechstunde kann gestartet werden: 10 Min vor Beginn bis 30 Min nach dem Ende.
     */
    public function canStart(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->status === AppointmentStatus::Booked
            && $now->greaterThanOrEqualTo($this->starts_at->copy()->subMinutes(self::EARLY_START_MINUTES))
            && $now->lessThanOrEqualTo($this->ends_at->copy()->addMinutes(self::LATE_START_MINUTES));
    }

    /**
     * Patienten können absagen, solange der Termin noch nicht begonnen hat.
     */
    public function canBeCancelledByPatient(?Carbon $now = null): bool
    {
        return $this->status === AppointmentStatus::Booked && $this->starts_at->isAfter($now ?? now());
    }

    /**
     * z. B. „Do., 16.10.2026 · 10:30–10:45 Uhr“.
     */
    public function when(): string
    {
        return $this->starts_at->locale('de')->translatedFormat('D, d.m.Y').' · '
            .$this->starts_at->format('H:i').'–'.$this->ends_at->format('H:i').' Uhr';
    }
}
