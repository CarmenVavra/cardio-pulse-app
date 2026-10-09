<?php

namespace Tests\Feature;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function record(int $systolic, int $diastolic): Measurement
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)->post('/app/messungen', [
            'systolic' => $systolic,
            'diastolic' => $diastolic,
            'pulse' => 80,
            'method' => 'manual',
            'symptoms' => ['keine'],
        ]);

        return Measurement::query()->latest('id')->firstOrFail();
    }

    public function test_diastolic_grade_3_raises_an_alarm(): void
    {
        $measurement = $this->record(150, 112);

        $this->assertSame(BloodPressureStatus::Red, $measurement->status);
        $this->assertDatabaseHas('alarms', ['measurement_id' => $measurement->id]);

        $this->get(route('patient.measurements.show', $measurement))
            ->assertSee('Hypertonie Grad 3 · schwerer Hochdruck')
            ->assertSee('Notruf 144');
    }

    public function test_grade_1_shows_grade_and_advice_without_alarm(): void
    {
        $measurement = $this->record(145, 92);

        $this->assertSame(BloodPressureStatus::Amber, $measurement->status);
        $this->assertDatabaseMissing('alarms', ['measurement_id' => $measurement->id]);

        $this->get(route('patient.measurements.show', $measurement))
            ->assertSee('Hypertonie Grad 1')
            ->assertSee('milder Hochdruck')
            ->assertSee('Eine einzelne Messung ist noch keine Diagnose.');
    }

    public function test_emergency_number_is_configurable(): void
    {
        config(['cardiopulse.emergency_number' => '112']);

        $measurement = $this->record(190, 125);

        $this->get(route('patient.measurements.show', $measurement))
            ->assertSee('tel:112')
            ->assertSee('Notruf 112')
            ->assertDontSee('Notruf 144');
        $this->get(route('patient.doctor'))->assertSee('<b class="emergency-number">112</b>', false);
    }

    public function test_hospital_report_lists_the_grade(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 165, 95);

        $this->actingAs($this->staff())
            ->get(route('patients.report', $patient))
            ->assertOk()
            ->assertSee('Hypertonie Grad 2');
    }
}
