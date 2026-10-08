<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Triage-Reihenfolge geändert: Zu niedrig (Blau) gilt jetzt als dringlicher als Normal (Grün).
 * Die beim Versand gespeicherte "schlechteste Farbe" der Monatsberichte wird neu berechnet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->recalculate(['red', 'amber', 'blue', 'green']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->recalculate(['red', 'amber', 'green', 'blue']);
    }

    /**
     * @param  list<string>  $order  dringlichster Status zuerst
     */
    private function recalculate(array $order): void
    {
        DB::table('monthly_reports')->orderBy('id')->each(function (object $report) use ($order) {
            $month = Carbon::parse($report->month)->startOfMonth();

            $statuses = DB::table('measurements')
                ->where('patient_id', $report->patient_id)
                ->whereBetween('measured_at', [$month, $month->copy()->endOfMonth()])
                ->distinct()
                ->pluck('status')
                ->all();

            $worst = collect($order)->first(fn (string $status) => in_array($status, $statuses, true));

            DB::table('monthly_reports')->where('id', $report->id)->update(['worst_status' => $worst]);
        });
    }
};
