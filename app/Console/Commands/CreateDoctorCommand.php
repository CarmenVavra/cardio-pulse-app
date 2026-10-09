<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\DoctorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Legt einen Arzt-Zugang auf der Kommandozeile an – z. B. den ersten Zugang
 * nach dem Deployment, wenn keine Demo-Daten eingespielt werden. Gibt es noch
 * keinen Admin, wird der neue Arzt Admin (sonst nur mit --admin).
 */
class CreateDoctorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cardiopulse:create-doctor {--admin : Admin-Rechte vergeben (darf Ärzte verwalten)}';

    /**
     * @var string
     */
    protected $description = 'Arzt-Zugang für den Überwachungsscreen anlegen';

    public function handle(DoctorService $doctors): int
    {
        $data = [
            'title' => $this->ask('Titel (z. B. Dr., leer lassen für keinen)') ?: null,
            'name' => (string) $this->ask('Vor- und Nachname'),
            'username' => mb_strtolower(trim((string) $this->ask('Benutzerkennung (z. B. m.weber)'))),
            'email' => (string) $this->ask('E-Mail'),
            'phone' => $this->ask('Telefon (optional)') ?: null,
            'available_until' => null,
            'password' => (string) $this->secret('Passwort (mind. 8 Zeichen, Buchstaben und Ziffern)'),
            'pin' => (string) $this->secret('PIN für den Privacy-Lock (6 Ziffern)'),
        ];

        $validator = Validator::make($data, [
            'title' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', Password::defaults()],
            'pin' => ['required', 'digits:6'],
        ], [], [
            'name' => 'Vor- und Nachname',
            'username' => 'Benutzerkennung',
            'email' => 'E-Mail',
            'password' => 'Passwort',
            'pin' => 'PIN',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $doctor = $doctors->create(
            [
                'title' => $data['title'],
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'available_until' => null,
            ],
            $data['password'],
            $data['pin'],
            null,
            $this->option('admin') || ! User::query()->where('role', UserRole::Staff)->where('is_admin', true)->exists(),
        );

        $this->info($doctor->displayName().' wurde angelegt'.($doctor->is_admin ? ' (Admin)' : '').'. Anmeldung mit „'.$doctor->username.'“.');

        return self::SUCCESS;
    }
}
