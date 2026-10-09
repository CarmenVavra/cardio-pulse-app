<?php

namespace Tests\Feature;

use App\Enums\AlarmType;
use App\Models\Alarm;
use App\Models\Patient;
use App\Models\User;
use App\Services\AlarmService;
use App\Services\EmergencyService;
use App\Services\MeasurementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SosTest extends TestCase
{
    use RefreshDatabase;

    private const VIENNA = ['lat' => 48.208176, 'lng' => 16.373819, 'accuracy' => 15];

    private function consentingPatient(array $attributes = []): Patient
    {
        return $this->patient(['location_consent_at' => now(), ...$attributes]);
    }

    public function test_every_patient_page_links_to_the_sos_page(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient->user);

        $this->get(route('patient.home'))->assertSee('Notfall · SOS')->assertSee(route('patient.sos'));
        $this->get(route('patient.doctor'))->assertSee('Notfall · SOS');

        $this->get(route('patient.sos'))
            ->assertOk()
            ->assertSee('2 Sekunden')
            ->assertSee('data-sos-button', false)
            ->assertSee('Ihr Standort wird <b>nicht</b> mitgeschickt', false)
            ->assertSee('tel:144')
            // Ortung muss für die eigene App erlaubt sein, sonst kommt nie ein Standort an.
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=(), usb=(), interest-cohort=()');
    }

    public function test_pressing_sos_alarms_the_hospital_with_location(): void
    {
        $patient = $this->consentingPatient();

        $this->actingAs($patient->user)
            ->postJson(route('patient.sos.store'), self::VIENNA)
            ->assertOk()
            ->assertJson(['open' => true, 'located' => true]);

        $alarm = Alarm::query()->sole();
        $this->assertSame(AlarmType::Sos, $alarm->type);
        $this->assertNull($alarm->measurement_id);
        $this->assertSame(['lat' => 48.208176, 'lng' => 16.373819, 'accuracy' => 15], $alarm->location);
        $this->assertNotNull($alarm->located_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.sos_triggered', 'user_id' => $patient->user->id]);

        // Standort ist verschlüsselt gespeichert.
        $raw = DB::table('alarms')->value('location');
        $this->assertStringNotContainsString('48.208', (string) $raw);

        $this->get(route('patient.sos'))
            ->assertSee('Das Krankenhaus ist alarmiert')
            ->assertSee('Notruf 144 anrufen')
            ->assertSee('Ihr Standort wurde an das Krankenhaus übermittelt.')
            ->assertDontSee('data-sos-button', false);
    }

    public function test_pressing_again_does_not_create_a_second_alarm(): void
    {
        $patient = $this->consentingPatient();
        $this->actingAs($patient->user);

        $this->postJson(route('patient.sos.store'))->assertOk()->assertJson(['located' => false]);
        $this->postJson(route('patient.sos.store'), self::VIENNA)->assertOk()->assertJson(['located' => true]);

        $this->assertSame(1, Alarm::query()->count());
        $this->assertNotNull(Alarm::query()->sole()->location);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.sos_repeated']);
    }

    public function test_location_is_only_stored_with_consent(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient->user);

        $this->postJson(route('patient.sos.store'), self::VIENNA)->assertOk()->assertJson(['open' => true, 'located' => false]);
        $this->postJson(route('patient.sos.location'), self::VIENNA)->assertOk()->assertJson(['located' => false]);

        $this->assertNull(Alarm::query()->sole()->location);
        $this->get(route('patient.sos'))->assertSee('Ihr Standort wird nicht übermittelt (keine Freigabe).');
    }

    public function test_location_can_follow_after_the_alarm(): void
    {
        $patient = $this->consentingPatient();
        $this->actingAs($patient->user);

        $this->post(route('patient.sos.store'))->assertRedirect(route('patient.sos'));
        $this->postJson(route('patient.sos.location'), self::VIENNA)->assertOk()->assertJson(['located' => true]);
        $this->postJson(route('patient.sos.location'), ['lat' => 'x'])->assertUnprocessable();

        $this->assertSame(48.208176, Alarm::query()->sole()->location['lat']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.location_received']);
    }

    public function test_broken_coordinates_never_stop_the_alarm(): void
    {
        $patient = $this->consentingPatient();

        $this->actingAs($patient->user)
            ->postJson(route('patient.sos.store'), ['lat' => '999', 'lng' => 'abc', 'accuracy' => '-5'])
            ->assertOk()
            ->assertJson(['open' => true, 'located' => false]);

        $this->assertSame(1, Alarm::query()->count());
    }

    public function test_hospital_sees_the_emergency_first_with_map_link(): void
    {
        $doctor = $this->staff();
        $older = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        app(MeasurementRecorder::class)->record($older, ['systolic' => 192, 'diastolic' => 124]);
        $patient = $this->consentingPatient(['first_name' => 'Anna', 'last_name' => 'Notfall']);
        app(EmergencyService::class)->trigger($patient, self::VIENNA);

        $this->actingAs($doctor)
            ->get(route('board'))
            ->assertSee('NOTFALL · NOTFALLTASTE')
            ->assertSee('NOTFALL – PATIENT HAT DIE NOTFALLTASTE GEDRÜCKT')
            ->assertSee('Anna Notfall')
            ->assertSee('https://www.openstreetmap.org/?mlat=48.208176&amp;mlon=16.373819', false)
            ->assertSee('48.208176, 16.373819 (± 15 m)')
            ->assertSee('Ich übernehme')
            ->assertSeeInOrder(['tag--sos', 'Anna Notfall', 'Josef Brandner'], false);

        $this->getJson(route('board.live'))
            ->assertJson(['open_alarms' => 2, 'unclaimed_alarms' => 2, 'alarm_passive' => false]);
    }

    public function test_simultaneous_alarms_are_spread_across_doctors(): void
    {
        $weber = $this->staff();
        $krause = User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
        $first = $this->patient(['first_name' => 'Anna', 'last_name' => 'Erste']);
        $second = $this->patient(['first_name' => 'Bruno', 'last_name' => 'Zweiter']);
        $alarmA = app(EmergencyService::class)->trigger($first);
        $this->travel(5)->seconds();
        $alarmB = app(EmergencyService::class)->trigger($second);

        // Beide sehen zuerst den älteren Notfall.
        $this->actingAs($krause)->getJson(route('board.live'))->assertJson(['alarm_id' => $alarmA->id]);

        // Dr. Weber übernimmt ihn …
        $this->actingAs($weber)->postJson(route('alarms.claim', $alarmA))->assertOk()->assertJson(['claimed' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.claimed', 'user_id' => $weber->id]);

        // … und behält ihn im Fenster, Dr. Krause bekommt den nächsten freien.
        $this->getJson(route('board.live'))->assertJson(['alarm_id' => $alarmA->id, 'unclaimed_alarms' => 1]);
        $this->actingAs($krause)->getJson(route('board.live'))->assertJson(['alarm_id' => $alarmB->id, 'alarm_passive' => false]);

        // Wer zu spät übernimmt, überschreibt nicht.
        $this->postJson(route('alarms.claim', $alarmA))->assertOk()->assertJson(['claimed' => false, 'claimed_by' => 'Dr. M. Weber']);
        $this->assertSame($weber->id, $alarmA->fresh()->claimed_by);

        $this->postJson(route('alarms.claim', $alarmB))->assertOk();
        $this->getJson(route('board.live'))->assertJson(['unclaimed_alarms' => 0]);

        // Der Patient sieht, wer sich kümmert.
        $this->actingAs($first->user)->getJson(route('patient.sos.status'))
            ->assertJson(['open' => true, 'claimed_by' => 'Dr. Miriam Weber']);
    }

    public function test_alarm_claimed_by_a_colleague_does_not_pop_up(): void
    {
        $weber = $this->staff();
        $krause = User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
        $alarm = app(EmergencyService::class)->trigger($this->patient());
        app(AlarmService::class)->claim($alarm, $weber);

        $this->actingAs($krause)->getJson(route('board.live'))->assertJson(['alarm_id' => $alarm->id, 'alarm_passive' => true]);
        $this->get(route('board'))->assertSee('Übernommen von Dr. M. Weber')->assertSee('data-alarm-layer  hidden', false);
    }

    public function test_acknowledging_deletes_the_location(): void
    {
        $doctor = $this->staff();
        $patient = $this->consentingPatient();
        $alarm = app(EmergencyService::class)->trigger($patient, self::VIENNA);

        $this->actingAs($doctor)
            ->postJson(route('alarms.acknowledge', $alarm), ['note' => 'Rettung mit Standort verständigt'])
            ->assertOk();

        $alarm->refresh();
        $this->assertNull($alarm->location);
        $this->assertNotNull($alarm->acknowledged_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.acknowledged', 'user_id' => $doctor->id]);

        $this->get(route('audit.index'))
            ->assertSee('Notfall (SOS)')
            ->assertSee('Standort gelöscht: ja');

        $this->actingAs($patient->user)->get(route('patient.sos'))
            ->assertSee('wurde vom Krankenhaus bearbeitet')
            ->assertSee('data-sos-button', false);
    }

    public function test_patient_reports_a_false_alarm_but_hospital_must_still_acknowledge(): void
    {
        $patient = $this->patient(['first_name' => 'Anna', 'last_name' => 'Fehl']);
        $this->actingAs($patient->user);
        $this->postJson(route('patient.sos.store'))->assertOk();

        $this->post(route('patient.sos.false-alarm'))->assertRedirect(route('patient.sos'));
        $this->get(route('patient.sos'))->assertSee('Sie haben einen Fehlalarm gemeldet.');

        $alarm = Alarm::query()->sole();
        $this->assertTrue($alarm->isOpen());
        $this->assertNotNull($alarm->false_alarm_at);

        $this->actingAs($this->staff())->get(route('board'))->assertSee('Fehlalarm – keine Hilfe nötig.');

        // Erneutes Drücken nimmt den Fehlalarm zurück.
        app(EmergencyService::class)->trigger($patient);
        $this->assertNull($alarm->fresh()->false_alarm_at);
    }

    public function test_patient_gives_and_withdraws_location_consent(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient->user);

        $this->get(route('patient.account.edit'))->assertSee('Standort im Notfall')->assertSee('Nicht freigegeben');

        $this->put(route('patient.account.location'), ['consent' => '1'])
            ->assertRedirect(route('patient.account.edit').'#standort');
        $this->assertNotNull($patient->fresh()->location_consent_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.location_consent_given']);

        $this->postJson(route('patient.sos.store'), self::VIENNA)->assertJson(['located' => true]);

        // Widerruf löscht auch den schon übermittelten Standort.
        $this->put(route('patient.account.location'), ['consent' => '0']);
        $this->assertNull($patient->fresh()->location_consent_at);
        $this->assertNull(Alarm::query()->sole()->location);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.location_consent_withdrawn']);
    }

    public function test_only_patients_trigger_and_only_staff_claim(): void
    {
        $patient = $this->patient();
        $alarm = app(EmergencyService::class)->trigger($patient);

        $this->actingAs($this->staff())->post(route('patient.sos.store'))->assertForbidden();
        $this->actingAs($patient->user)->post(route('alarms.claim', $alarm))->assertForbidden();
    }
}
