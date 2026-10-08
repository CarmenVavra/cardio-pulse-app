<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Chat-Nachricht zwischen Klinik und Patient. read_at gilt für den jeweiligen Empfänger.
 *
 * @property bool $from_patient
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class Message extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'user_id',
        'from_patient',
        'body',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_patient' => 'boolean',
            'read_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeFromPatient(Builder $query): void
    {
        $query->where('from_patient', true);
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeFromClinic(Builder $query): void
    {
        $query->where('from_patient', false);
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Absender aus Sicht der Klinik bzw. des Patienten.
     */
    public function senderName(): string
    {
        if ($this->from_patient) {
            return $this->patient->fullName();
        }

        return $this->user?->displayName() ?? 'Klinik';
    }
}
