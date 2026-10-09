<?php

namespace App\Models;

use App\Enums\BloodPressureCategory;
use App\Enums\BloodPressureStatus;
use Database\Factories\MeasurementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property BloodPressureStatus $status
 * @property Carbon $measured_at
 * @property list<string>|null $symptoms
 */
class Measurement extends Model
{
    /** @use HasFactory<MeasurementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'systolic',
        'diastolic',
        'pulse',
        'status',
        'method',
        'symptoms',
        'rested',
        'medication_taken',
        'measured_at',
        'symptom_free_confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BloodPressureStatus::class,
            'symptoms' => 'array',
            'rested' => 'boolean',
            'medication_taken' => 'boolean',
            'measured_at' => 'datetime',
            'symptom_free_confirmed_at' => 'datetime',
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
     * @return HasOne<Alarm, $this>
     */
    public function alarm(): HasOne
    {
        return $this->hasOne(Alarm::class);
    }

    public function reading(): string
    {
        return $this->systolic.'/'.$this->diastolic;
    }

    /**
     * Einteilung nach ESC/ESH, z. B. „Hypertonie Grad 1“.
     */
    public function category(): BloodPressureCategory
    {
        return BloodPressureCategory::classify($this->systolic, $this->diastolic);
    }

    /**
     * Symptome als lesbare Liste, z. B. "Brustdruck, Kopfschmerz" oder "—".
     */
    public function symptomLabels(): string
    {
        $labels = config('cardiopulse.symptoms');

        $names = array_map(fn (string $key) => $labels[$key] ?? $key, $this->symptoms ?? []);

        return $names === [] ? '—' : implode(', ', $names);
    }
}
