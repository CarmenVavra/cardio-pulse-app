<?php

namespace App\Models;

use App\Enums\AlarmType;
use App\Enums\SosResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Alarm im Krankenhaus – durch einen gefährlich hohen Messwert oder die Notfalltaste (SOS).
 *
 * @property int $patient_id
 * @property int|null $measurement_id
 * @property AlarmType $type
 * @property array{lat: float, lng: float, accuracy: int|null}|null $location
 * @property Carbon|null $located_at
 * @property int|null $claimed_by
 * @property Carbon|null $claimed_at
 * @property Carbon|null $false_alarm_at
 * @property SosResponse|null $patient_response
 * @property Carbon|null $responded_at
 * @property int|null $rescue_called_by
 * @property Carbon|null $rescue_called_at
 * @property Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 */
class Alarm extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'measurement',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'measurement_id',
        'type',
        'location',
        'located_at',
        'claimed_by',
        'claimed_at',
        'false_alarm_at',
        'patient_response',
        'responded_at',
        'rescue_called_by',
        'rescue_called_at',
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
            'type' => AlarmType::class,
            // Standort ist besonders schutzwürdig – verschlüsselt mit APP_KEY.
            'location' => 'encrypted:array',
            'located_at' => 'datetime',
            'claimed_at' => 'datetime',
            'false_alarm_at' => 'datetime',
            'patient_response' => SosResponse::class,
            'responded_at' => 'datetime',
            'rescue_called_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'acknowledged_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rescueCalledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescue_called_by')->withTrashed();
    }

    /**
     * @param  Builder<Alarm>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('acknowledged_at');
    }

    /**
     * @param  Builder<Alarm>  $query
     */
    public function scopeSos(Builder $query): void
    {
        $query->where('type', AlarmType::Sos);
    }

    public function isOpen(): bool
    {
        return $this->acknowledged_at === null;
    }

    public function isSos(): bool
    {
        return $this->type === AlarmType::Sos;
    }

    public function isClaimed(): bool
    {
        return $this->claimed_by !== null;
    }

    public function isClaimedByOther(?User $user): bool
    {
        return $this->claimed_by !== null && $this->claimed_by !== $user?->id;
    }

    public function isRescueCalled(): bool
    {
        return $this->rescue_called_at !== null;
    }

    /**
     * Wer ruft die Rettung? Stand für Krankenhaus und Patient:
     * rescue_called – Krankenhaus hat die Rettung verständigt,
     * false_alarm – Patient meldet Fehlalarm,
     * hospital – Patient bittet das Krankenhaus, die Rettung zu rufen,
     * self – Patient ruft laut eigener Angabe selbst an (nicht bestätigt),
     * no_response – keine Angabe innerhalb der Frist: gilt wie „hospital“,
     * waiting – Patient wurde gerade erst gefragt.
     * Bei Alarmen durch Messwerte nur rescue_called oder null.
     */
    public function rescueState(): ?string
    {
        if ($this->isRescueCalled()) {
            return 'rescue_called';
        }
        if (! $this->isSos()) {
            return null;
        }
        if ($this->false_alarm_at !== null) {
            return 'false_alarm';
        }
        if ($this->patient_response !== null) {
            return $this->patient_response->value;
        }

        $deadline = $this->triggered_at->copy()->addSeconds((int) config('cardiopulse.sos_response_seconds'));

        return now()->greaterThanOrEqualTo($deadline) ? 'no_response' : 'waiting';
    }

    /**
     * Das Krankenhaus muss die Rettung rufen (oder zumindest sofort zurückrufen).
     */
    public function needsRescueByHospital(): bool
    {
        return $this->isOpen() && in_array($this->rescueState(), ['hospital', 'no_response'], true);
    }

    /**
     * Ändert sich, wenn sich der angezeigte Stand ändert (übernommen, Standort, Fehlalarm,
     * Rückmeldung, Rettung verständigt, Frist abgelaufen) – das Alarm-Fenster wird dann
     * aktualisiert, ohne die getippte Maßnahme zu verlieren.
     */
    public function version(): string
    {
        return implode('-', [
            $this->id,
            $this->claimed_by ?? 0,
            $this->located_at?->getTimestamp() ?? 0,
            $this->false_alarm_at?->getTimestamp() ?? 0,
            $this->responded_at?->getTimestamp() ?? 0,
            $this->rescueState() ?? 'none',
        ]);
    }

    /**
     * Link auf OpenStreetMap mit Markierung – zum Weitergeben an die Rettung.
     */
    public function mapUrl(): ?string
    {
        if ($this->location === null) {
            return null;
        }

        ['lat' => $lat, 'lng' => $lng] = $this->location;

        return sprintf('https://www.openstreetmap.org/?mlat=%1$.6F&mlon=%2$.6F#map=17/%1$.6F/%2$.6F', $lat, $lng);
    }

    /**
     * Koordinaten zum Vorlesen am Telefon, z. B. „48.208176, 16.373819 (± 15 m)“.
     */
    public function coordinates(): ?string
    {
        if ($this->location === null) {
            return null;
        }

        $text = sprintf('%.6F, %.6F', $this->location['lat'], $this->location['lng']);

        return $this->location['accuracy'] !== null ? $text.' (± '.$this->location['accuracy'].' m)' : $text;
    }
}
