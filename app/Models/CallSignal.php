<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Nachricht des WebRTC-Verbindungsaufbaus (offer, answer, candidate, ready).
 *
 * @property int $call_id
 * @property string $sender staff|patient
 * @property string $type
 * @property array<string, mixed> $payload
 */
class CallSignal extends Model
{
    public const UPDATED_AT = null;

    public const STAFF = 'staff';

    public const PATIENT = 'patient';

    public const TYPES = ['ready', 'offer', 'answer', 'candidate'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'call_id',
        'sender',
        'type',
        'payload',
    ];

    /**
     * Verschlüsselt, weil die Netzwerkkandidaten IP-Adressen enthalten.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Call, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
