<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Sicherung der SQLite-Datenbank im laufenden Betrieb.
 *
 * „VACUUM INTO“ schreibt einen in sich stimmigen Schnappschuss – auch wenn
 * gerade Messwerte gespeichert werden (ein einfaches Kopieren der Datei könnte
 * eine halb geschriebene Datenbank erwischen).
 */
class BackupService
{
    /** So viele Sicherungen bleiben auf dem Server liegen; ältere werden gelöscht. */
    public const KEEP = 14;

    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Legt einen Schnappschuss an und gibt den absoluten Pfad zurück.
     */
    public function create(): string
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Die Sicherung unterstützt nur SQLite.');
        }

        File::ensureDirectoryExists($this->directory(), 0700);

        $path = $this->directory().'/cardiopulse-'.now()->format('Y-m-d_His').'.sqlite';
        if (File::exists($path)) {
            throw new RuntimeException('Sicherung existiert bereits: '.basename($path));
        }

        DB::statement('VACUUM INTO ?', [$path]);
        chmod($path, 0600);

        $this->prune();

        return $path;
    }

    /**
     * Ältere Sicherungen löschen, nur die neuesten {@see KEEP} behalten.
     *
     * @return list<string> gelöschte Dateinamen
     */
    public function prune(): array
    {
        $files = File::glob($this->directory().'/cardiopulse-*.sqlite');
        rsort($files);

        $deleted = [];
        foreach (array_slice($files, self::KEEP) as $file) {
            File::delete($file);
            $deleted[] = basename($file);
        }

        return $deleted;
    }
}
