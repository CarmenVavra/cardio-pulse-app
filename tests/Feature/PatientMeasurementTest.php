<?php

namespace Tests\Feature;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientMeasurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_last_measurement(): void
    {
        $patient = $this->patient(['first_name' => 'Josef']);
        $this->measurement($patient, 128, 82, ['pulse' => 72]);

        $this->actingAs($patient->user)
            ->get('/app')
            ->assertOk()
            ->assertSee('Josef')
            ->assertSee('128/82')
            ->assertSee('Blutdruck eintragen')
            ->assertSee('Monatsbericht');
    }

    public function test_measure_form_is_rendered(): void
    {
        $this->actingAs($this->patient()->user)
            ->get('/app/messen')
            ->assertOk()
            ->assertSee('OBERER')
            ->assertSee('Beschwerden?')
            ->assertSee('Foto-Scan');
    }

    public function test_amber_measurement_shows_result_screen(): void
    {
        $patient = $this->patient();

        $response = $this->actingAs($patient->user)->post('/app/messungen', [
            'systolic' => 136,
            'diastolic' => 86,
            'pulse' => 76,
            'method' => 'manual',
            'symptoms' => ['keine'],
        ]);

        $measurement = Measurement::firstOrFail();
        $response->assertRedirect(route('patient.measurements.show', $measurement));
        $this->assertSame(BloodPressureStatus::Amber, $measurement->status);
        $this->assertSame([], $measurement->symptoms);

        $this->get(route('patient.measurements.show', $measurement))
            ->assertOk()
            ->assertSee('Hoch-normal')
            ->assertSee('Vorfeld des Bluthochdrucks')
            ->assertSee('136/86')
            ->assertSee('Gespeichert &amp; ans Krankenhaus übertragen', false);
    }

    public function test_red_measurement_shows_emergency_screen_and_alarm(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)->post('/app/messungen', [
            'systolic' => 192,
            'diastolic' => 124,
            'pulse' => 98,
            'method' => 'manual',
            'symptoms' => ['brustdruck'],
        ]);

        $measurement = Measurement::firstOrFail();

        $this->get(route('patient.measurements.show', $measurement))
            ->assertOk()
            ->assertSee('GEFÄHRLICH HOHER WERT')
            ->assertSee('Hypertensive Krise')
            ->assertSee('Notruf 144')
            ->assertSee('tel:144')
            ->assertSee('Das Krankenhaus wurde sofort benachrichtigt');

        $this->assertDatabaseHas('alarms', ['measurement_id' => $measurement->id]);
    }

    public function test_symptom_free_confirmation_requires_checkbox(): void
    {
        $patient = $this->patient();
        $measurement = $this->measurement($patient, 190, 121);

        $this->actingAs($patient->user)
            ->post(route('patient.measurements.confirm', $measurement))
            ->assertSessionHasErrors('symptom_free');

        $this->post(route('patient.measurements.confirm', $measurement), ['symptom_free' => '1'])
            ->assertRedirect(route('patient.home'));

        $this->assertNotNull($measurement->fresh()->symptom_free_confirmed_at);
    }

    public function test_validation_rejects_implausible_values(): void
    {
        $this->actingAs($this->patient()->user)
            ->post('/app/messungen', ['systolic' => 80, 'diastolic' => 90, 'method' => 'manual'])
            ->assertSessionHasErrors('systolic');

        $this->post('/app/messungen', ['systolic' => 400, 'diastolic' => 90, 'method' => 'manual'])
            ->assertSessionHasErrors('systolic');

        $this->post('/app/messungen', ['systolic' => 120, 'diastolic' => 80, 'method' => 'fax'])
            ->assertSessionHasErrors('method');

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_patient_cannot_view_foreign_measurement(): void
    {
        $other = $this->measurement($this->patient(), 120, 80);

        $this->actingAs($this->patient()->user)
            ->get(route('patient.measurements.show', $other))
            ->assertStatus(403);
    }
}
