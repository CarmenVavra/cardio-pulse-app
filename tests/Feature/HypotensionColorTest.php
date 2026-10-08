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
