<?php

namespace Tests\Feature;

use App\Models\MonthlyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_month_overview_shows_calendar_and_stats(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 120, 75, ['measured_at' => '2026-09-03 08:00']);
        $this->measurement($patient, 190, 110, ['measured_at' => '2026-09-03 19:00']);

        $this->actingAs($patient->user)
            ->get('/app/monat/2026-09')
            ->assertOk()
            ->assertSee('September 2026')
            ->assertSee('3. September 2026: Gefährlich hoch')
            ->assertSee('Ihr Monatsbericht ist bereit');
    }

    public function test_current_month_cannot_be_sent_before_month_end(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 120, 75, ['measured_at' => '2026-10-02 08:00']);

        $this->actingAs($patient->user)
            ->get('/app/monat')
            ->assertSee('ab dem <b>31.10.</b>', false);

        $this->post('/app/monat/2026-10/senden')->assertSessionHasErrors('month');
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_previous_month_report_is_sent_to_hospital(): void
    {
        $patient = $this->patient(['first_name' => 'Piotr', 'last_name' => 'Nowak']);
        $this->measurement($patient, 150, 95, ['measured_at' => '2026-09-10 08:00']);
        $this->measurement($patient, 140, 85, ['measured_at' => '2026-09-11 08:00']);

        $this->actingAs($patient->user)
            ->post('/app/monat/2026-09/senden')
            ->assertRedirect(route('patient.month', '2026-09'));

        $report = MonthlyReport::firstOrFail();
        $this->assertSame(2, $report->measurement_count);
        $this->assertSame(145, $report->avg_systolic);
        $this->assertDatabaseHas('audit_logs', ['action' => 'monthly_report.sent']);

        $this->actingAs($this->staff())
            ->get('/monatsberichte?month=2026-09')
            ->assertOk()
            ->assertSee('Piotr Nowak')
            ->assertSee('Monatsverlauf September 2026');
    }

    public function test_report_can_be_sent_again_and_is_updated(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 150, 95, ['measured_at' => '2026-09-10 08:00']);

        $this->actingAs($patient->user)->post('/app/monat/2026-09/senden')->assertSessionHasNoErrors();

        // Nachträglich erfasste Messung, dann erneut senden.
        $this->measurement($patient, 120, 75, ['measured_at' => '2026-09-20 08:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00'));

        $this->post('/app/monat/2026-09/senden')
            ->assertRedirect(route('patient.month', '2026-09'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('monthly_reports', 1);
        $report = MonthlyReport::firstOrFail();
        $this->assertSame(2, $report->measurement_count);
        $this->assertSame('2026-10-09 12:00:00', $report->sent_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'monthly_report.resent']);
    }

    public function test_seeded_style_report_can_be_resent(): void
    {
        // Bericht wie vom Seeder angelegt (Monat als Datumsstring übergeben).
        $patient = $this->patient();
        $this->measurement($patient, 150, 95, ['measured_at' => '2026-09-10 08:00']);
        $patient->monthlyReports()->create([
            'month' => '2026-09-01',
            'sent_at' => '2026-09-30 19:10:00',
            'measurement_count' => 1,
        ]);

        $this->actingAs($patient->user)
            ->post('/app/monat/2026-09/senden')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('patient.month', '2026-09'));

        $this->assertDatabaseCount('monthly_reports', 1);
    }

    public function test_future_month_is_not_found(): void
    {
        $this->actingAs($this->patient()->user)->get('/app/monat/2027-01')->assertNotFound();
    }

    public function test_pdf_view_for_general_practitioner(): void
    {
        $patient = $this->patient();
        $this->measurement($patient, 150, 95, ['measured_at' => '2026-09-10 08:00']);

        $this->actingAs($patient->user)
            ->get('/app/monat/2026-09/pdf')
            ->assertOk()
            ->assertSee('Monatsbericht September 2026')
            ->assertSee('150/95');
    }
}
