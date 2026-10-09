<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ampel nach ESC/ESH: Rot beginnt jetzt bei Hypertonie Grad 3 (diastolisch ≥ 110 statt ≥ 120 mmHg).
 * Gespeicherte Messungen mit diastolisch 110–119 werden umgefärbt und die „schlechteste
 * Farbe“ der Monatsberichte neu berechnet. Für alte Messungen entstehen keine Alarme.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('measurements')
            ->where('systolic', '<', 180)
            ->whereBetween('diastolic', [110, 119])
            ->where('status', '!=', 'red')
            ->update(['status' => 'red']);

        $this->recalculateMonthlyReports();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $affected = DB::table('measurements')
            ->where('systolic', '<', 180)
            ->whereBetween('diastolic', [110, 119])
            ->where('status', 'red');

        (clone $affected)->where('systolic', '<', 90)->update(['status' => 'blue']);
        (clone $affected)->where('systolic', '>=', 90)->update(['status' => 'amber']);

        $this->recalculateMonthlyReports();
    }

    private function recalculateMonthlyReports(): void
    {
        $order = ['red', 'amber', 'blue', 'green'];

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
