<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $updated_at
 */
class Medication extends Model
{
    /**
     * Erlaubte Mengen je Einnahmezeitpunkt (Schema morgens–mittags–abends).
     */
    public const AMOUNTS = ['0', '½', '1', '1½', '2', '3'];

    public const SEPARATOR = '–';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'name',
        'dose',
        'schedule',
        'updated_by',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Wer die Medikation zuletzt erfasst oder geändert hat (Arzt oder Patient).
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /**
     * Schema in seine drei Teile zerlegen, z. B. "1–0–½" → ['1', '0', '½'].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function scheduleParts(): array
    {
        $parts = array_map('trim', preg_split('/[–-]/u', (string) $this->schedule) ?: []);

        return [$parts[0] ?? '0', $parts[1] ?? '0', $parts[2] ?? '0'];
    }

    public static function composeSchedule(string $morning, string $noon, string $evening): string
    {
        return implode(self::SEPARATOR, [$morning, $noon, $evening]);
    }

    public function changedByPatient(): bool
    {
        return $this->updatedBy?->isPatient() ?? false;
    }
}
