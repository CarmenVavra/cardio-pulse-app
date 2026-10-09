<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Notfall-Zugang: Admin-Rechte auf dem Server vergeben, falls kein Admin mehr
 * erreichbar ist (in der App vergeben Admins die Rechte unter „Ärzte“).
 */
class MakeAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cardiopulse:make-admin {username : Benutzerkennung des Arztes}';

    /**
     * @var string
     */
    protected $description = 'Einem Arzt Admin-Rechte geben (darf Ärzte verwalten)';

    public function handle(): int
    {
        $doctor = User::query()
            ->where('role', UserRole::Staff)
            ->where('username', mb_strtolower(trim((string) $this->argument('username'))))
            ->first();

        if ($doctor === null) {
            $this->error('Kein aktiver Arzt mit dieser Benutzerkennung gefunden.');

            return self::FAILURE;
        }

        if (! $doctor->is_admin) {
            $doctor->is_admin = true;
            $doctor->save();
            AuditLog::record('doctor.updated', $doctor, ['fields' => ['is_admin'], 'via' => 'console']);
        }

        $this->info($doctor->displayName().' ist Admin.');

        return self::SUCCESS;
    }
}
