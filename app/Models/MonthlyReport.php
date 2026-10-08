<?php

namespace App\Models;

use App\Enums\BloodPressureStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $month
 * @property Carbon $sent_at
 * @property BloodPressureStatus|null $worst_status
 */
class MonthlyReport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'month',
        'sent_at',
        'measurement_count',
        'avg_systolic',
        'avg_diastolic',
        'worst_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'sent_at' => 'datetime',
            'worst_status' => BloodPressureStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
