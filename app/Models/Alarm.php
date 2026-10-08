<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 */
class Alarm extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'measurement_id',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'action_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
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
     * @return BelongsTo<Measurement, $this>
     */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @param  Builder<Alarm>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('acknowledged_at');
    }

    public function isOpen(): bool
    {
        return $this->acknowledged_at === null;
    }
}
