<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Schlüsselpaar für Push-Benachrichtigungen (VAPID) erzeugen – einmalig pro Server.
 * Mit --write werden die Schlüssel direkt in die .env geschrieben.
 */
class VapidKeysCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cardiopulse:vapid-keys
                            {--write : Schlüssel in die .env eintragen}
                            {--force : Vorhandene Schlüssel ersetzen (alle Patienten müssen Benachrichtigungen neu einschalten)}';

    /**
     * @var string
     */
    protected $description = 'Schlüssel für Push-Benachrichtigungen der Patienten-App erzeugen';

    public function handle(): int
    {
        $existing = (string) config('cardiopulse.push.public_key') !== '';

        if ($existing && ! $this->option('force')) {
            $this->info('Push-Schlüssel sind bereits eingerichtet – nichts zu tun.');
            $this->line('Neue Schlüssel nur mit --force (danach müssen alle Patienten die Benachrichtigungen neu einschalten).');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        $lines = [
            'VAPID_PUBLIC_KEY='.$keys['publicKey'],
            'VAPID_PRIVATE_KEY='.$keys['privateKey'],
        ];

        if (! $this->option('write')) {
            $this->line('In die .env eintragen (den privaten Schlüssel geheim halten):');
            $this->newLine();
            foreach ($lines as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();
        if (! is_file($path) || ! is_writable($path)) {
            $this->output->getErrorStyle()->error('Die .env-Datei fehlt oder ist nicht beschreibbar: '.$path);

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);
        // Alte Einträge entfernen (bei --force), dann die neuen anhängen.
        $env = (string) preg_replace('/^VAPID_(PUBLIC|PRIVATE)_KEY=.*\R?/m', '', $env);
        $env = rtrim($env)."\n\n# Push-Benachrichtigungen (nicht ändern)\n".implode("\n", $lines)."\n";
        file_put_contents($path, $env);

        $this->info('Push-Schlüssel wurden in die .env eingetragen. Push-Benachrichtigungen sind jetzt eingeschaltet.');

        return self::SUCCESS;
    }
}
