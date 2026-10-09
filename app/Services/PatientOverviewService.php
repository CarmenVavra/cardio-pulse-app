<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use App\Support\MeasurementStats;
use App\Support\PatientOverview;
use App\Support\TrendChart;
use Illuminate\Support\Carbon;

/**
 * Detailansicht (Patient / Monatsbericht) mit 30-Tage-Verlauf, Kennzahlen, Medikation, Nachrichten und Terminen.
 */
class PatientOverviewService
{
    public const MESSAGE_LIMIT = 10;

    public function __construct(private readonly MessageService $messages) {}

    public function build(Patient $patient, Carbon $from, Carbon $to): PatientOverview
    {
        $patient->loadMissing([
            'medications.updatedBy',
            'latestMeasurement',
            'latestAlarm.acknowledgedBy',
            'latestMonthlyReport',
        ]);

        $measurements = $patient->measurements()
            ->whereBetween('measured_at', [$from, $to])
            ->orderBy('measured_at')
            ->get();

        $recent = $patient->measurements()
            ->latest('measured_at')
            ->limit(4)
            ->get();

        $appointments = $patient->appointments()->upcoming()->with('doctor')->orderBy('starts_at')->get();
        $pastAppointments = $patient->appointments()
            ->whereNotIn('id', $appointments->modelKeys())
            ->with('doctor')
            ->latest('starts_at')
            ->limit(3)
            ->get();
        $doctors = User::query()->where('role', UserRole::Staff)->orderBy('name')->get();

        return new PatientOverview(
            patient: $patient,
            from: $from,
            to: $to,
            stats: MeasurementStats::from($measurements),
            chart: new TrendChart($measurements, $from, $to),
            recentUploads: $recent,
            messages: $this->messages->thread($patient, self::MESSAGE_LIMIT),
            appointments: $appointments,
            pastAppointments: $pastAppointments,
            doctors: $doctors,
            appointmentDefaults: $this->appointmentDefaults($patient),
        );
    }

    /**
     * Vorschlag für das Formular: nächste volle Viertelstunde in einer Stunde, behandelnder Arzt.
     *
     * @return array{date: string, time: string, doctor_id: ?int}
     */
    private function appointmentDefaults(Patient $patient): array
    {
        $start = now()->addHour()->ceilMinutes(15);

        return [
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'doctor_id' => $patient->doctor_id ?? auth()->id(),
        ];
    }
}
