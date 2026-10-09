<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AlarmService;
use App\Services\MeasurementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function colleague(): User
    {
        return User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
    }

    public function test_only_admins_see_the_log(): void
    {
        $admin = $this->staff();
        $colleague = $this->colleague();

        $this->actingAs($colleague)->get(route('board'))->assertDontSee(route('audit.index'));
        $this->get(route('audit.index'))->assertForbidden();
        $this->get(route('audit.export'))->assertForbidden();

        $this->actingAs($admin)->get(route('board'))->assertSee(route('audit.index'));
        $this->get(route('audit.index'))->assertOk()->assertSee('Protokoll');
    }

    public function test_entries_are_readable_with_patient_and_actor(): void
    {
        $admin = $this->staff();
        $patient = $this->patient();
        $measurement = app(MeasurementRecorder::class)->record($patient, ['systolic' => 192, 'diastolic' => 124]);
        app(AlarmService::class)->acknowledge($measurement->alarm()->firstOrFail(), $admin, 'Patient angerufen');
        AuditLog::record('doctor.updated', $admin, ['fields' => ['email', 'is_admin']], $admin);
        AuditLog::record('auth.failed', null, ['username' => 'unbekannt']);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Alarm quittiert')
            ->assertSee('Messung übertragen')
            ->assertSee('Messung 192/124 · '.$patient->fullName().' ('.$patient->patient_number.')')
            ->assertSee('Arzt geändert')
            ->assertSee('Geänderte Felder: E-Mail, Admin-Rechte')
            ->assertSee('Anmeldung fehlgeschlagen')
            ->assertSee('Benutzerkennung: unbekannt')
            ->assertSee('Status: Rot')
            ->assertSee('Dr. Miriam Weber');
    }

    public function test_deleted_patients_stay_visible(): void
    {
        $admin = $this->staff();
        $patient = $this->patient();
        AuditLog::record('patient.updated', $patient, [], $admin);
        $patient->delete();

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertSee($patient->fullName().' ('.$patient->patient_number.') – gelöscht');
    }

    public function test_filters_by_group_user_and_date(): void
    {
        $admin = $this->staff();
        $colleague = $this->colleague();
        AuditLog::record('auth.login', null, [], $admin);
        AuditLog::record('screen.locked', null, [], $colleague);
        $old = AuditLog::record('patient.created', null, [], $admin);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAs($admin);

        $this->get(route('audit.index', ['group' => 'screen']))
            ->assertSee('Bildschirm gesperrt')
            ->assertDontSee('Angemeldet');

        $this->get(route('audit.index', ['user_id' => $colleague->id]))
            ->assertSee('Bildschirm gesperrt')
            ->assertDontSee('Angemeldet');

        $this->get(route('audit.index', ['from' => now()->subDay()->toDateString()]))
            ->assertSee('Angemeldet')
            ->assertDontSee('Patient angelegt');

        $this->get(route('audit.index', ['from' => '2026-10-09', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_long_logs_are_paginated(): void
    {
        $admin = $this->staff();
        foreach (range(1, 55) as $i) {
            AuditLog::record('auth.login', null, ['department' => 'telemonitoring'], $admin);
        }

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertSee('Seite 1 von 2')
            ->assertSee('Ältere ›');

        $this->get(route('audit.index', ['page' => 2]))->assertSee('Seite 2 von 2')->assertSee('‹ Neuere');
    }

    public function test_csv_export_is_excel_ready_and_protected_against_formulas(): void
    {
        $admin = $this->staff();
        $attacker = User::factory()->staff()->create(['title' => null, 'name' => '=HYPERLINK("http://boese.example")']);
        AuditLog::record('auth.login', null, [], $attacker);
        AuditLog::record('auth.failed', null, ['username' => 'unbekannt']);

        $response = $this->actingAs($admin)->get(route('audit.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('cardiopulse-protokoll-', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFZeit;Bereich;Aktion;Benutzer;Betroffen;Details;IP-Adresse", $csv);
        $this->assertStringContainsString('Anmeldung fehlgeschlagen', $csv);
        $this->assertStringContainsString(";\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';"=HYPERLINK', $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit.exported', 'user_id' => $admin->id]);
    }
}
