<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAndMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fhir_export_contains_blood_pressure_observations(): void
    {
        $patient = $this->patient(['patient_number' => 'CP-10482']);
        $this->measurement($patient, 192, 124, ['pulse' => 98]);

        $response = $this->actingAs($this->staff())
            ->get(route('patients.fhir', $patient))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json; charset=utf-8');

        $bundle = $response->json();
        $this->assertSame('Bundle', $bundle['resourceType']);
        $this->assertSame('Patient', $bundle['entry'][0]['resource']['resourceType']);

        $bp = $bundle['entry'][1]['resource'];
        $this->assertSame('85354-9', $bp['code']['coding'][0]['code']);
        $this->assertSame('8480-6', $bp['component'][0]['code']['coding'][0]['code']);
        $this->assertSame(192, $bp['component'][0]['valueQuantity']['value']);
        $this->assertSame('8462-4', $bp['component'][1]['code']['coding'][0]['code']);
        $this->assertSame('HH', $bp['interpretation'][0]['coding'][0]['code']);

        $this->assertSame('8867-4', $bundle['entry'][2]['resource']['code']['coding'][0]['code']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.fhir']);
    }

    public function test_print_report_is_rendered(): void
    {
        $patient = $this->patient(['first_name' => 'Karin', 'last_name' => 'Hofmann']);
        $this->measurement($patient, 168, 102);

        $this->actingAs($this->staff())
            ->get(route('patients.report', $patient))
            ->assertOk()
            ->assertSee('Blutdruck-Verlaufsbericht')
            ->assertSee('Karin Hofmann')
            ->assertSee('KIS-Export (HL7 FHIR R4)');
    }

    public function test_staff_message_appears_in_patient_app(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->staff())
            ->post(route('patients.messages.store', $patient), ['body' => 'Bitte morgen erneut messen.'])
            ->assertSessionHas('status');

        $this->actingAs($patient->user)
            ->get('/app')
            ->assertSee('1 ungelesen');

        $this->get('/app/arzt')
            ->assertOk()
            ->assertSee('Bitte morgen erneut messen.')
            ->assertSee('Mein Behandlungsteam');

        $this->assertNotNull($patient->messages()->first()->read_at);
    }

    public function test_message_body_is_required(): void
    {
        $this->actingAs($this->staff())
            ->post(route('patients.messages.store', $this->patient()), ['body' => ''])
            ->assertSessionHasErrors('body');
    }
}
