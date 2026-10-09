<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Gelöschte Patienten werden nur ausgeblendet (Soft Delete): Die Behandlungsdokumentation
 * unterliegt der Aufbewahrungspflicht (§ 630f BGB, 10 Jahre).
 *
 * @property Carbon $birth_date
 * @property Carbon|null $deleted_at
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'doctor_id',
        'patient_number',
        'first_name',
        'last_name',
        'birth_date',
        'street',
        'postal_code',
        'city',
        'phone',
        'diagnosis',
        'gp_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id')->withTrashed();
    }

    /**
     * @return HasMany<Measurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /**
     * Termine für Videosprechstunden.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasOne<Measurement, $this>
     */
    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(Measurement::class)->latestOfMany('measured_at');
    }

    /**
     * @return HasMany<Alarm, $this>
     */
    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }

    /**
     * @return HasOne<Alarm, $this>
     */
    public function latestAlarm(): HasOne
    {
        return $this->hasOne(Alarm::class)->latestOfMany('triggered_at');
    }

    /**
     * @return HasMany<MonthlyReport, $this>
     */
    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    /**
     * @return HasOne<MonthlyReport, $this>
     */
    public function latestMonthlyReport(): HasOne
    {
        return $this->hasOne(MonthlyReport::class)->latestOfMany('sent_at');
    }

    /**
     * @return HasMany<Medication, $this>
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    /**
     * @return HasMany<Call, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function age(): int
    {
        return (int) $this->birth_date->age;
    }

    public function address(): string
    {
        return $this->street.', '.$this->postal_code.' '.$this->city;
    }
}
