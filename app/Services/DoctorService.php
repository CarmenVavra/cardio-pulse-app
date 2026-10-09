<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ärzte (Krankenhaus-Personal) anlegen, bearbeiten und löschen.
 */
class DoctorService
{
    public function __construct(private readonly CallService $calls) {}

    /**
     * @param  array{title: ?string, name: string, username: string, email: string, phone: ?string, available_until: ?string}  $data
     */
    public function create(array $data, string $password, string $pin, ?User $by = null, bool $isAdmin = false): User
    {
        $doctor = new User([
            ...$data,
            'role' => UserRole::Staff,
            'password' => $password,
            'pin' => $pin,
        ]);
        $doctor->is_admin = $isAdmin;
        $doctor->save();

        AuditLog::record('doctor.created', $doctor, ['username' => $doctor->username, 'is_admin' => $isAdmin], $by);

        return $doctor;
    }

    /**
     * @param  array{title: ?string, name: string, username: string, email: string, phone: ?string, available_until: ?string}  $data
     */
    public function update(User $doctor, array $data, ?string $password, ?string $pin, User $by, bool $isAdmin): User
    {
        $doctor->fill($data);
        $doctor->is_admin = $isAdmin;

        if ($password !== null && $password !== '') {
            $doctor->password = $password;
        }
        if ($pin !== null && $pin !== '') {
            $doctor->pin = $pin;
        }

        // Nur Feldnamen protokollieren – keine Passwörter oder PINs.
        $changed = array_keys($doctor->getDirty());
        $doctor->save();

        AuditLog::record('doctor.updated', $doctor, ['fields' => $changed], $by);

        return $doctor;
    }

    /**
     * Soft Delete: Anmeldung wird gesperrt, Patienten werden dem Vertretungsarzt zugewiesen.
     */
    public function delete(User $doctor, User $by, ?User $replacement): void
    {
        DB::transaction(function () use ($doctor, $by, $replacement) {
            $reassigned = 0;
            if ($replacement !== null) {
                $reassigned = $doctor->patients()->update(['doctor_id' => $replacement->id]);
            }

            Call::query()->where('user_id', $doctor->id)->open()->get()->each(fn (Call $call) => $this->calls->end($call));

            // Geplante Videosprechstunden übernimmt die Vertretung.
            if ($replacement !== null) {
                Appointment::query()->where('user_id', $doctor->id)->upcoming()->update(['user_id' => $replacement->id]);
            }

            // Sitzungen und "Angemeldet bleiben" beenden.
            User::query()->whereKey($doctor->id)->update(['remember_token' => Str::random(60)]);
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $doctor->id)->delete();
            }

            $doctor->delete();

            AuditLog::record('doctor.deleted', $doctor, [
                'username' => $doctor->username,
                'patients_reassigned' => $reassigned,
                'replacement_id' => $replacement?->id,
            ], $by);
        });
    }
}
