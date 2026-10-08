<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Services\MeasurementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlarmTest extends TestCase
{
    use RefreshDatabase;

    public function test_red_measurement_triggers_alarm(): void
    {
        $patient = $this->patient();

        app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124]);
        app(MeasurementRecorder::class)->record($patient, ['systolic' => 150, 'diastolic' => 90]);

        $this->assertDatabaseCount('alarms', 1);
        $this->assertNull(Alarm::first()->acknowledged_at);
    }

    public function test_board_shows_alarm_banner_and_modal(): void
    {
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124, 'symptoms' => ['brustdruck']]);

        $this->actingAs($this->staff())
            ->get('/ueberwachung')
            ->assertSee('GEFÄHRLICH HOHER WERT')
            ->assertSee('GEFÄHRLICH HOHER BLUTDRUCK')
            ->assertSee('Maßnahme dokumentieren')
            ->assertSee('Brustdruck');
    }

    public function test_staff_acknowledges_alarm_with_note(): void
    {
        $staff = $this->staff();
        $patient = $this->patient();
        app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124]);
        $alarm = Alarm::firstOrFail();

        $this->actingAs($staff)
            ->postJson(route('alarms.acknowledge', $alarm), ['note' => 'Rettungsdienst alarmiert'])
            ->assertOk()
            ->assertJson(['acknowledged' => true]);

        $alarm->refresh();
        $this->assertNotNull($alarm->acknowledged_at);
        $this->assertSame($staff->id, $alarm->acknowledged_by);
        $this->assertSame('Rettungsdienst alarmiert', $alarm->action_note);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.acknowledged', 'user_id' => $staff->id]);

        $this->get('/ueberwachung')->assertSee('Alarm quittiert');
    }

    public function test_patient_cannot_acknowledge_alarm(): void
    {
        $patient = $this->patient();
        app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124]);

        $this->actingAs($patient->user)
            ->post(route('alarms.acknowledge', Alarm::firstOrFail()))
            ->assertStatus(403);
    }
}
