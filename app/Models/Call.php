<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CallDirection $direction
 * @property CallStatus $status
 * @property Carbon|null $answered_at
 * @property Carbon|null $ended_at
 * @property Carbon $created_at
 */
class Call extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'user_id',
        'measurement_id',
        'direction',
        'status',
        'answered_at',
        'ended_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CallDirection::class,
            'status' => CallStatus::class,
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Arzt / Mitarbeitende des Krankenhauses.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Measurement, $this>
     */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    /**
     * @param  Builder<Call>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [CallStatus::Ringing->value, CallStatus::Active->value]);
    }

    public function durationSeconds(): int
    {
        if ($this->answered_at === null) {
            return 0;
        }

        return (int) $this->answered_at->diffInSeconds($this->ended_at ?? now());
    }

    public function durationLabel(): string
    {
        $minutes = (int) ceil($this->durationSeconds() / 60);

        return $minutes.' Min';
    }
}
