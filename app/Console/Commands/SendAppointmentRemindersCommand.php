<?php

namespace App\Console\Commands;

use App\Services\AppointmentService;
use Illuminate\Console\Command;

/**
 * Terminerinnerungen verschicken – läuft alle 5 Minuten über `php artisan schedule:run`
 * (geplante Aufgabe am Server), kann aber auch von Hand gestartet werden.
 */
class SendAppointmentRemindersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cardiopulse:send-reminders';

    /**
     * @var string
     */
    protected $description = 'E-Mail-Erinnerung an Patienten, deren Videosprechstunde bald beginnt';

    public function handle(AppointmentService $appointments): int
    {
        $sent = $appointments->sendDueReminders();

        $this->line($sent === 1 ? '1 Erinnerung verschickt.' : $sent.' Erinnerungen verschickt.');

        return self::SUCCESS;
    }
}
