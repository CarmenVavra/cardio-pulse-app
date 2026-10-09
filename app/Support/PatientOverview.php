<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Measurement;
use App\Models\Message;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PatientOverview
{
    /**
     * @param  Collection<int, Measurement>  $recentUploads
     * @param  Collection<int, Message>  $messages
     * @param  Collection<int, Appointment>  $appointments  geplante Videosprechstunden
     * @param  Collection<int, Appointment>  $pastAppointments  die letzten durchgeführten/abgesagten
     * @param  Collection<int, User>  $doctors  Auswahl „Termin mit“
     * @param  array{date: string, time: string, doctor_id: ?int}  $appointmentDefaults
     */
    public function __construct(
        public readonly Patient $patient,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly MeasurementStats $stats,
        public readonly TrendChart $chart,
        public readonly Collection $recentUploads,
        public readonly Collection $messages,
        public readonly Collection $appointments,
        public readonly Collection $pastAppointments,
        public readonly Collection $doctors,
        public readonly array $appointmentDefaults,
    ) {}
}
