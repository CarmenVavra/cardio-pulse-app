<?php

namespace Database\Seeders;

use App\Enums\BloodPressureStatus;
use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\UserRole;
use App\Models\Alarm;
use App\Models\Call;
use App\Models\Measurement;
use App\Models\MonthlyReport;
use App\Models\Patient;
use App\Models\User;
use App\Support\MeasurementStats;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo-Daten gemäß UI-Mockups (Klinikum Nord · Kardiologie K3).
 *
 * Testzugänge (nur lokale Entwicklung):
 *   Krankenhaus:  Benutzerkennung "m.weber", Passwort "cardiopulse", PIN "123456"
 *   Patienten-App: "josef.brandner@cardiopulse.test" (bzw. vorname.nachname@cardiopulse.test), Passwort "cardiopulse"
 */
class DatabaseSeeder extends Seeder
{
    public const PASSWORD = 'cardiopulse';

    public const PIN = '123456';

    /**
     * Name, Alter, ID, sys, dia, Puls, Upload vor (Min), Art, Symptome, Ort, Straße, PLZ, Telefon.
     *
     * @var list<array{0: string, 1: int, 2: string, 3: int, 4: int, 5: int, 6: int, 7: string, 8: list<string>, 9: string, 10: string, 11: string, 12: string}>
     */
    private const PATIENTS = [
        ['Josef Brandner', 68, 'CP-10482', 192, 124, 98, 2, 'single', ['brustdruck', 'kopfschmerz'], 'Hamburg', 'Lindenallee 14', '22529', '0171 2245 810'],
        ['Selin Aydın', 54, 'CP-10377', 184, 116, 104, 9, 'single', ['kopfschmerz'], 'Norderstedt', 'Ulzburger Straße 212', '22850', '0160 9182 334'],
        ['Karin Hofmann', 61, 'CP-10211', 168, 102, 82, 12, 'single', ['schwindel'], 'Pinneberg', 'Damm 41', '25421', '0152 4410 278'],
        ['Piotr Nowak', 72, 'CP-10590', 152, 96, 76, 25, 'report', [], 'Hamburg', 'Holstenstraße 88', '22767', '0176 3321 904'],
        ['Ingrid Schulte', 79, 'CP-10102', 141, 88, 70, 41, 'single', ['kopfschmerz'], 'Ahrensburg', 'Große Straße 7', '22926', '04102 55 812'],
        ['Anna Meier', 58, 'CP-10655', 126, 82, 68, 60, 'report', [], 'Hamburg', 'Eppendorfer Weg 150', '20253', '0170 6672 115'],
        ['Hans Becker', 70, 'CP-10318', 124, 80, 66, 65, 'single', [], 'Wedel', 'Bahnhofstraße 23', '22880', '04103 81 440'],
        ['Thomas Keller', 49, 'CP-10731', 118, 76, 72, 120, 'single', [], 'Hamburg', 'Winterhuder Weg 31', '22085', '0157 2290 613'],
        ['Maria Rossi', 66, 'CP-10044', 121, 79, 64, 180, 'report', [], 'Quickborn', 'Kieler Straße 99', '25451', '0163 7751 028'],
        ['Lena Vogt', 45, 'CP-10812', 86, 56, 88, 18, 'single', ['schwindel'], 'Hamburg', 'Barmbeker Straße 5', '22303', '0151 8830 442'],
        ['Erich Wolf', 81, 'CP-10267', 88, 58, 92, 52, 'single', ['schwindel', 'muedigkeit'], 'Elmshorn', 'Königstraße 18', '25335', '04121 29 337'],
    ];

    private const MEDICATIONS = [
        [['Ramipril', '10 mg', '1–0–0'], ['Amlodipin', '5 mg', '0–0–1'], ['HCT', '12,5 mg', '1–0–0']],
        [['Candesartan', '16 mg', '1–0–0'], ['Amlodipin', '10 mg', '1–0–0']],
        [['Valsartan', '160 mg', '1–0–0'], ['Bisoprolol', '5 mg', '1–0–0']],
        [['Ramipril', '5 mg', '1–0–1']],
        [['Losartan', '50 mg', '1–0–0'], ['HCT', '12,5 mg', '1–0–0']],
        [['Candesartan', '8 mg', '1–0–0']],
        [['Ramipril', '5 mg', '1–0–0'], ['Amlodipin', '5 mg', '0–0–1']],
        [['Valsartan', '80 mg', '1–0–0']],
        [['Lisinopril', '10 mg', '1–0–0']],
        [['Bisoprolol', '2,5 mg', '1–0–0']],
        [['Ramipril', '2,5 mg', '1–0–0'], ['Torasemid', '5 mg', '1–0–0']],
    ];

    public function run(): void
    {
        Model::unguard();

        $doctor = User::create([
            'role' => UserRole::Staff,
            'username' => 'm.weber',
            'title' => 'Dr.',
            'name' => 'Miriam Weber',
            'email' => 'm.weber@klinikum-nord.test',
            'phone' => '040 1234 5671',
            'password' => self::PASSWORD,
            'pin' => self::PIN,
            'available_until' => '16:00',
        ]);

        User::create([
            'role' => UserRole::Staff,
            'username' => 't.krause',
            'title' => 'Dr.',
            'name' => 'Tobias Krause',
            'email' => 't.krause@klinikum-nord.test',
            'password' => self::PASSWORD,
            'pin' => self::PIN,
            'available_until' => '22:00',
        ]);

        $now = now();

        foreach (self::PATIENTS as $index => $row) {
            [$name, $age, $number, $sys, $dia, $pulse, $offset, $kind, $symptoms, $city, $street, $postal, $phone] = $row;
            [$first, $last] = explode(' ', $name, 2);

            $user = User::create([
                'role' => UserRole::Patient,
                'name' => $name,
                'email' => Str::slug($first, '.').'.'.Str::slug($last, '.').'@cardiopulse.test',
                'phone' => $phone,
                'password' => self::PASSWORD,
            ]);

            $patient = Patient::create([
                'user_id' => $user->id,
                'doctor_id' => $doctor->id,
                'patient_number' => $number,
                'first_name' => $first,
                'last_name' => $last,
                'birth_date' => $now->copy()->subYears($age)->subDays(40 + $index * 23),
                'street' => $street,
                'postal_code' => $postal,
                'city' => $city,
                'phone' => $phone,
                'diagnosis' => $sys >= 160 ? 'Hypertonie Grad 2' : ($sys < 90 ? 'Hypertonie, medikamentös überkorrigiert' : 'Hypertonie Grad 1'),
                'gp_name' => ['Dr. Lenz', 'Dr. Petersen', 'Dr. Yilmaz', 'Dr. Brandt'][$index % 4],
            ]);

            foreach (self::MEDICATIONS[$index] as [$medName, $dose, $schedule]) {
                $patient->medications()->create(['name' => $medName, 'dose' => $dose, 'schedule' => $schedule, 'updated_by' => $doctor->id]);
            }

            $uploadAt = $now->copy()->subMinutes($offset);
            $latestAt = $kind === 'report' ? $uploadAt->copy()->subMinutes(35) : $uploadAt;

            $this->history($patient, $doctor, $sys, $dia, $pulse, $latestAt, $index);

            $latest = $this->measurement($patient, $sys, $dia, $pulse, $latestAt, $symptoms);

            if ($latest->status === BloodPressureStatus::Red) {
                // Josef Brandner: offener Alarm (Signalton) – Selin Aydın: bereits quittiert.
                $this->alarm($patient, $latest, $index === 0 ? null : $doctor, 'Patientin zu Hause angerufen, Amlodipin vorgezogen, Kontrollmessung in 30 Min.');
            }

            $this->monthlyReport($patient, $index, $kind === 'report' ? $uploadAt : null);
        }

        $this->callsAndMessages($doctor);

        Model::reguard();
    }

    /**
     * 40 Tage Verlauf mit 1–3 Messungen pro Tag, Trend zum aktuellen Wert.
     */
    private function history(Patient $patient, User $doctor, int $sys, int $dia, int $pulse, Carbon $latestAt, int $seed): void
    {
        mt_srand(1000 + $seed);
        $ratio = $sys < 90 ? $dia / $sys : min($dia / $sys, 0.62);
        $days = 40;
        $start = match (true) {
            $sys >= 180 => 128,
            $sys >= 130 => $sys - 6,
            $sys < 90 => 98,
            default => $sys + 4,
        };

        for ($d = $days; $d >= 0; $d--) {
            $day = $latestAt->copy()->subDays($d)->startOfDay();
            $count = mt_rand(1, 3);
            $times = [];
            for ($k = 0; $k < $count; $k++) {
                $times[] = $day->copy()->addMinutes(mt_rand(390, 1290));
            }
            sort($times);

            foreach ($times as $time) {
                if ($time->greaterThanOrEqualTo($latestAt->copy()->subMinutes(40))) {
                    continue;
                }

                $progress = 1 - $d / $days;
                $target = min($sys, 168);
                $base = $start + ($target - $start) * $progress;
                $noise = $sys < 90 ? 7 : 12;
                $s = (int) round($base + (mt_rand(-100, 100) / 100) * $noise);
                $di = (int) round($s * $ratio + mt_rand(-5, 4));
                $p = max(50, $pulse + mt_rand(-8, 8));

                $symptoms = [];
                if ($s >= 165 && mt_rand(0, 2) === 0) {
                    $symptoms = ['kopfschmerz'];
                } elseif ($s < 92 && mt_rand(0, 2) === 0) {
                    $symptoms = ['schwindel'];
                }

                $measurement = $this->measurement($patient, $s, $di, $p, $time, $symptoms);

                if ($measurement->status === BloodPressureStatus::Red) {
                    $this->alarm($patient, $measurement, $doctor, 'Telefonisch kontaktiert, Kontrollmessung vereinbart.');
                }
            }
        }
    }

    /**
     * @param  list<string>  $symptoms
     */
    private function measurement(Patient $patient, int $sys, int $dia, int $pulse, Carbon $at, array $symptoms): Measurement
    {
        return $patient->measurements()->create([
            'systolic' => $sys,
            'diastolic' => $dia,
            'pulse' => $pulse,
            'status' => BloodPressureStatus::classify($sys, $dia),
            'method' => mt_rand(0, 2) === 0 ? 'bluetooth' : 'manual',
            'symptoms' => $symptoms,
            'rested' => true,
            'medication_taken' => true,
            'measured_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function alarm(Patient $patient, Measurement $measurement, ?User $acknowledgedBy, string $note): void
    {
        Alarm::create([
            'patient_id' => $patient->id,
            'measurement_id' => $measurement->id,
            'triggered_at' => $measurement->measured_at,
            'acknowledged_at' => $acknowledgedBy ? $measurement->measured_at->copy()->addMinutes(mt_rand(2, 6)) : null,
            'acknowledged_by' => $acknowledgedBy?->id,
            'action_note' => $acknowledgedBy ? $note : null,
        ]);
    }

    /**
     * Monatsbericht des Vormonats (9 von 11 Patienten haben ihn gesendet).
     */
    private function monthlyReport(Patient $patient, int $index, ?Carbon $sentAt): void
    {
        if (in_array($patient->patient_number, ['CP-10731', 'CP-10812'], true)) {
            return;
        }

        $month = now()->subMonthNoOverflow()->startOfMonth();
        $measurements = $patient->measurements()
            ->whereBetween('measured_at', [$month, $month->copy()->endOfMonth()])
            ->get();

        if ($measurements->isEmpty()) {
            return;
        }

        $stats = MeasurementStats::from($measurements);

        MonthlyReport::create([
            'patient_id' => $patient->id,
            'month' => $month->toDateString(),
            'sent_at' => $sentAt ?? $month->copy()->endOfMonth()->setTime(19, 10 + $index * 3),
            'measurement_count' => $stats->count,
            'avg_systolic' => $stats->avgSystolic,
            'avg_diastolic' => $stats->avgDiastolic,
            'worst_status' => $stats->worst,
        ]);
    }

    private function callsAndMessages(User $doctor): void
    {
        $josef = Patient::query()->where('patient_number', 'CP-10482')->firstOrFail();

        $calls = [
            [CallDirection::ToClinic, now()->subDays(16)->setTime(10, 12), 4],
            [CallDirection::ToPatient, now()->subDays(8)->setTime(8, 45), 6],
        ];

        foreach ($calls as [$direction, $at, $minutes]) {
            Call::create([
                'patient_id' => $josef->id,
                'user_id' => $doctor->id,
                'direction' => $direction,
                'status' => CallStatus::Ended,
                'answered_at' => $at->copy()->addSeconds(12),
                'ended_at' => $at->copy()->addMinutes($minutes),
                'note' => 'Werte besprochen, Medikation unverändert.',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $josef->messages()->create([
            'user_id' => $doctor->id,
            'body' => 'Guten Tag Herr Brandner, bitte messen Sie morgen früh vor der Tabletteneinnahme erneut und tragen Sie den Wert in der App ein.',
            'created_at' => now()->subDay()->setTime(17, 20),
        ]);
    }
}
