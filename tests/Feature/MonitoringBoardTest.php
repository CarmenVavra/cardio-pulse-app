<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_lists_patients_sorted_by_triage(): void
    {
        $green = $this->patient(['first_name' => 'Gerda', 'last_name' => 'Grün']);
        $red = $this->patient(['first_name' => 'Rolf', 'last_name' => 'Rot']);
        $white = $this->patient(['first_name' => 'Wilma', 'last_name' => 'Weiß']);
        $amber = $this->patient(['first_name' => 'Gustav', 'last_name' => 'Gelb']);

        $this->measurement($green, 120, 75);
        $this->measurement($red, 190, 110);
        $this->measurement($white, 85, 55);
        $this->measurement($amber, 150, 90);

        $this->actingAs($this->staff())
            ->get('/ueberwachung')
            ->assertStatus(200)
            ->assertSeeInOrder(['Rolf Rot', 'Gustav Gelb', 'Gerda Grün', 'Wilma Weiß'])
            ->assertSee('190/110')
            ->assertSee('Sofort anrufen')
            ->assertSee('Haftungsausschluss');
    }

    public function test_live_endpoint_returns_rows_counts_and_alarm(): void
    {
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        $measurement = $this->measurement($patient, 192, 124);
        $patient->alarms()->create(['measurement_id' => $measurement->id, 'triggered_at' => now()]);

        $this->actingAs($this->staff())
            ->getJson('/ueberwachung/live')
            ->assertOk()
            ->assertJsonPath('counts.red', 1)
            ->assertJsonPath('open_alarms', 1)
            ->assertJsonPath('locked', false)
            ->assertJsonStructure(['rows_html', 'banner_html', 'modal_html', 'uploads_today', 'patients_total']);
    }

    public function test_status_scope_skips_rows(): void
    {
        $this->actingAs($this->staff())
            ->getJson('/ueberwachung/live?scope=status')
            ->assertOk()
            ->assertJsonMissingPath('rows_html');
    }

    public function test_monthly_report_upload_is_shown_as_kind(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 125, 80, ['measured_at' => now()->subHour()]);
        $patient->monthlyReports()->create([
            'month' => now()->subMonth()->startOfMonth()->toDateString(),
            'sent_at' => now()->subMinutes(5),
            'measurement_count' => 30,
        ]);

        $this->actingAs($this->staff())
            ->get('/ueberwachung')
            ->assertSee('Monatsbericht')
            ->assertSee('vor 5 Min');
    }

    public function test_patient_detail_shows_chart_and_medication(): void
    {
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        $patient->medications()->create(['name' => 'Ramipril', 'dose' => '10 mg', 'schedule' => '1–0–0']);
        $this->measurement($patient, 150, 95, ['measured_at' => now()->subDays(3)]);
        $this->measurement($patient, 192, 124);

        $this->actingAs($this->staff())
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Josef Brandner')
            ->assertSee('30-Tage-Verlauf')
            ->assertSee('Ramipril')
            ->assertSee('192/124');
    }

    public function test_patients_index_redirects_to_most_urgent_patient(): void
    {
        $green = $this->patient();
        $red = $this->patient();
        $this->measurement($green, 120, 75);
        $this->measurement($red, 190, 110);

        $this->actingAs($this->staff())
            ->get('/patienten')
            ->assertRedirect(route('patients.show', $red));
    }

    public function test_demo_upload_creates_measurement(): void
    {
        $this->patient();

        $this->actingAs($this->staff())
            ->postJson('/demo/upload')
            ->assertOk();

        $this->assertDatabaseCount('measurements', 1);
    }
}
