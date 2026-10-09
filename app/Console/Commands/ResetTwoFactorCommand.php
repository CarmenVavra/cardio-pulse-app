<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;

/**
 * Notfall-Zugang: Zwei-Faktor-Anmeldung auf dem Server zurücksetzen, falls Handy
 * und Wiederherstellungscodes fehlen und kein anderer Admin erreichbar ist.
 */
class ResetTwoFactorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cardiopulse:reset-two-factor {username : Benutzerkennung des Arztes}';

    /**
     * @var string
     */
    protected $description = 'Zwei-Faktor-Anmeldung eines Arztes zurücksetzen (danach genügt wieder das Passwort)';

    public function handle(TwoFactorService $twoFactor): int
    {
        $doctor = User::query()
            ->where('role', UserRole::Staff)
            ->where('username', mb_strtolower(trim((string) $this->argument('username'))))
            ->first();

        if ($doctor === null) {
            $this->error('Kein aktiver Arzt mit dieser Benutzerkennung gefunden.');

            return self::FAILURE;
        }

        if (! $doctor->hasTwoFactor()) {
            $this->info($doctor->displayName().' hat keine Zwei-Faktor-Anmeldung eingerichtet.');

            return self::SUCCESS;
        }

        $twoFactor->disable($doctor, null);
        $this->info('Die Zwei-Faktor-Anmeldung von '.$doctor->displayName().' wurde zurückgesetzt.');

        return self::SUCCESS;
    }
}
