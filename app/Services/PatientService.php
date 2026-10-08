<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Patienten anlegen und bearbeiten – inkl. App-Zugang (Benutzerkonto).
 */
class PatientService
{
    public function __construct(private readonly CallService $calls) {}

    /**
     * @param  array<string, mixed>  $data  Stammdaten
     */
    public function create(array $data, string $email, string $password, User $by): Patient
    {
        return DB::transaction(function () use ($data, $email, $password, $by) {
            $user = User::create([
                'role' => UserRole::Patient,
                'name' => $data['first_name'].' '.$data['last_name'],
                'email' => $email,
                'phone' => $data['phone'],
                'password' => $password,
            ]);

            $patient = Patient::create([
                ...$data,
                'user_id' => $user->id,
                'patient_number' => $this->nextPatientNumber(),
            ]);

            AuditLog::record('patient.created', $patient, ['patient_number' => $patient->patient_number], $by);

            return $patient;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Stammdaten
     */
    public function update(Patient $patient, array $data, string $email, ?string $password, User $by): Patient
    {
        return DB::transaction(function () use ($patient, $data, $email, $password, $by) {
            $patient->fill($data);
            $changed = array_keys($patient->getDirty());
            $patient->save();

            $account = [
                'name' => $patient->fullName(),
                'email' => $email,
                'phone' => $patient->phone,
            ];
            if ($password !== null && $password !== '') {
                $account['password'] = $password;
                $changed[] = 'password';
            }

            if ($patient->user_id === null) {
                $user = User::create([...$account, 'role' => UserRole::Patient, 'password' => $password ?: bin2hex(random_bytes(16))]);
                $patient->update(['user_id' => $user->id]);
            } else {
                $user = $patient->user()->firstOrFail();
                $user->fill($account);
                if ($user->isDirty('email')) {
                    $changed[] = 'email';
                }
                $user->save();
            }

            AuditLog::record('patient.updated', $patient, ['fields' => array_values(array_unique($changed))], $by);

            return $patient;
        });
    }

    /**
     * Patient löschen (Soft Delete): verschwindet aus Überwachung und Listen, App-Zugang wird gesperrt.
     * Messwerte, Alarme und Protokolle bleiben wegen der Aufbewahrungspflicht erhalten.
     */
    public function delete(Patient $patient, User $by, ?string $reason = null): void
    {
        DB::transaction(function () use ($patient, $by, $reason) {
            // Laufende oder klingelnde Anrufe beenden.
            $patient->calls()->open()->get()->each(fn (Call $call) => $this->calls->end($call));

            $openAlarms = $patient->alarms()->open()->count();

            // Aktive App-Sitzungen und "Angemeldet bleiben" des Patienten beenden.
            if ($patient->user_id !== null) {
                User::query()->whereKey($patient->user_id)->update(['remember_token' => Str::random(60)]);

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $patient->user_id)->delete();
                }
            }

            $patient->delete();

            AuditLog::record('patient.deleted', $patient, [
                'patient_number' => $patient->patient_number,
                'reason' => $reason,
                'open_alarms' => $openAlarms,
            ], $by);
        });
    }

    /**
     * Fortlaufende Patientennummer, z. B. "CP-10813" (inkl. gelöschter Patienten, keine Wiederverwendung).
     */
    public function nextPatientNumber(): string
    {
        $highest = Patient::withTrashed()
            ->pluck('patient_number')
            ->map(fn (string $number) => (int) preg_replace('/\D/', '', $number))
            ->max() ?? 10000;

        return 'CP-'.max(10001, $highest + 1);
    }
}
