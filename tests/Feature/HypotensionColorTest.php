<?php

namespace Tests\Feature;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HypotensionColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_reading_is_shown_in_blue_in_app_and_hospital(): void
    {
        $patient = $this->patient(['first_name' => 'Bruno', 'last_name' => 'Blau']);

        $this->actingAs($patient->user)
            ->post(route('patient.measurements.store'), ['systolic' => 85, 'diastolic' => 55, 'pulse' => 64, 'method' => 'manual'])
            ->assertRedirect();

        $measurement = Measurement::query()->sole();
        $this->assertSame(BloodPressureStatus::Blue, $measurement->status);

        $this->get(route('patient.measurements.show', $measurement))
            ->assertOk()
            ->assertSee('result-head st-blue', false)
            ->assertSee('Zu niedrig');

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertOk()
            ->assertSee('board-row st-blue', false)
            ->assertSee('counter st-blue', false)
            ->assertDontSee('st-white', false);
    }

    public function test_low_values_are_ranked_before_normal_values(): void
    {
        $green = $this->patient(['first_name' => 'Gerda', 'last_name' => 'Grün']);
        $blue = $this->patient(['first_name' => 'Bruno', 'last_name' => 'Blau']);
        $this->measurement($green, 120, 75);
        $this->measurement($blue, 85, 55);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertSeeInOrder(['Zu niedrig', 'Normal'])
            ->assertSeeInOrder(['Bruno Blau', 'Gerda Grün']);
    }

    public function test_monthly_report_worst_status_is_recalculated_with_new_order(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 120, 75, ['measured_at' => '2026-09-10 08:00:00']);
        $this->measurement($patient, 85, 55, ['measured_at' => '2026-09-11 08:00:00']);
        $report = $patient->monthlyReports()->create([
            'month' => '2026-09-01',
            'worst_status' => 'green',
            'measurement_count' => 2,
            'avg_systolic' => 103,
            'avg_diastolic' => 65,
            'sent_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_08_000015_recalculate_monthly_report_worst_status.php');

        $migration->up();
        $this->assertSame(BloodPressureStatus::Blue, $report->fresh()?->worst_status);

        $migration->down();
        $this->assertSame(BloodPressureStatus::Green, $report->fresh()?->worst_status);
    }

    public function test_migration_renames_stored_white_status_to_blue_and_back(): void
    {
        $patient = $this->patient();
        $measurement = $this->measurement($patient, 85, 55);
        $migration = require database_path('migrations/2026_10_08_000014_rename_white_status_to_blue.php');

        $migration->down();
        $this->assertSame('white', DB::table('measurements')->where('id', $measurement->id)->value('status'));

        $migration->up();
        $this->assertSame('blue', DB::table('measurements')->where('id', $measurement->id)->value('status'));
        $this->assertSame(BloodPressureStatus::Blue, $measurement->fresh()?->status);
    }
}
