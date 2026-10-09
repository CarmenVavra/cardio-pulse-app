<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Datenbank sichern – auf dem Server per SSH, als geplante Aufgabe oder über backup.bat.
 * Gibt nur den Pfad der Sicherung aus, damit Skripte ihn weiterverwenden können.
 */
class BackupCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cardiopulse:backup';

    /**
     * @var string
     */
    protected $description = 'Schnappschuss der Datenbank nach storage/app/backups schreiben (die neuesten '.BackupService::KEEP.' bleiben erhalten)';

    public function handle(BackupService $backups): int
    {
        try {
            $path = $backups->create();
        } catch (RuntimeException $e) {
            // Fehler nach stderr, damit Skripte sie nicht für einen Pfad halten.
            $this->output->getErrorStyle()->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($path);

        return self::SUCCESS;
    }
}
