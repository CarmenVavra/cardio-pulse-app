<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Gelöschte Konten (Soft Delete) können sich nicht mehr anmelden, bleiben aber für
 * Quittierungen, Anrufe und das Prüfprotokoll nachvollziehbar.
 *
 * @property UserRole $role
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'username',
        'title',
        'name',
        'email',
        'phone',
        'password',
        'pin',
        'available_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * @return HasOne<Patient, $this>
     */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /**
     * Patienten, die diesem Arzt zugeordnet sind.
     *
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'doctor_id');
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isPatient(): bool
    {
        return $this->role === UserRole::Patient;
    }

    public function displayName(): string
    {
        return trim(($this->title ? $this->title.' ' : '').$this->name);
    }

    /**
     * Kurzform für die Kopfzeile, z. B. "Dr. M. Weber".
     */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $last = array_pop($parts) ?? '';
        $initials = implode(' ', array_map(fn (string $part) => mb_substr($part, 0, 1).'.', $parts));

        return trim(($this->title ? $this->title.' ' : '').trim($initials.' '.$last));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[count($parts) - 1] ?? '';

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
    }
}
