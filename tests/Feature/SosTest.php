<?php

namespace Tests\Feature;

use App\Enums\AlarmType;
use App\Enums\SosResponse;
use App\Models\Alarm;
use App\Models\AuditLog;
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
            ->assertSee('Ich rufe selbst 144 an')
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

        $doctor = $this->staff();
        $this->actingAs($doctor)->post(route('patient.sos.store'))->assertForbidden();
        $this->actingAs($doctor)->post(route('patient.sos.respond'), ['response' => 'self'])->assertForbidden();
        $this->actingAs($patient->user)->post(route('alarms.claim', $alarm))->assertForbidden();
        $this->actingAs($patient->user)->post(route('alarms.rescue', $alarm))->assertForbidden();
    }

    public function test_patient_is_asked_who_calls_the_rescue(): void
    {
        $patient = $this->patient();
        app(EmergencyService::class)->trigger($patient);

        $this->actingAs($patient->user)->get(route('patient.sos'))
            ->assertSee('Wer ruft die Rettung?')
            ->assertSee('Ich rufe selbst 144 an')
            ->assertSee('data-sos-self', false)
            ->assertSee('Ich kann nicht telefonieren – bitte Rettung rufen');

        $this->actingAs($this->staff())->get(route('board'))
            ->assertSee('Patient wird gefragt, wer die Rettung ruft.')
            ->assertSee('data-rescue-state="waiting"', false);
    }

    public function test_patient_calling_the_rescue_themselves_is_shown_but_not_confirmed(): void
    {
        $patient = $this->patient(['first_name' => 'Anna', 'last_name' => 'Selbst']);
        $this->actingAs($patient->user);
        app(EmergencyService::class)->trigger($patient);

        $this->postJson(route('patient.sos.respond'), ['response' => 'self'])
            ->assertOk()
            ->assertJson(['open' => true, 'response' => 'self', 'rescue_called' => false]);
        // Erneutes Tippen protokolliert nicht doppelt.
        $this->postJson(route('patient.sos.respond'), ['response' => 'self'])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'alarm.patient_calls_rescue')->count());

        $this->get(route('patient.sos'))
            ->assertSee('Sie haben angegeben, selbst den Notruf 144 anzurufen.')
            ->assertSee('Ich kann nicht telefonieren');

        // Auch nach Ablauf der Frist gilt die Angabe – kein „Keine Rückmeldung“.
        $this->travel(5)->minutes();
        $this->actingAs($this->staff())->get(route('board'))
            ->assertSee('ruft laut eigener Angabe selbst 144 an')
            ->assertSee('nicht bestätigt');
        $this->getJson(route('board.live'))->assertJson(['alarm_urgent' => false, 'urgent_alarms' => 0]);
    }

    public function test_patient_asks_the_hospital_to_call_the_rescue(): void
    {
        $doctor = $this->staff();
        $older = $this->patient(['first_name' => 'Bruno', 'last_name' => 'Frueher']);
        app(EmergencyService::class)->trigger($older);
        app(AlarmService::class)->claim(Alarm::query()->sole(), User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']));
        $this->travel(5)->seconds();

        $patient = $this->patient(['first_name' => 'Anna', 'last_name' => 'Hilfe']);
        $this->actingAs($patient->user);
        $this->postJson(route('patient.sos.store'))->assertOk();
        $this->post(route('patient.sos.false-alarm'));

        // Bitte um Rettung nimmt einen versehentlich gemeldeten Fehlalarm zurück.
        $this->post(route('patient.sos.respond'), ['response' => 'hospital'])->assertRedirect(route('patient.sos'));
        $alarm = $patient->alarms()->sole();
        $this->assertNull($alarm->false_alarm_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.patient_requests_rescue', 'user_id' => $patient->user->id]);

        $this->get(route('patient.sos'))
            ->assertSee('Sie haben das Krankenhaus gebeten, die Rettung zu rufen.')
            ->assertSee('Doch selbst 144 anrufen')
            ->assertDontSee('Ich kann nicht telefonieren');

        $this->actingAs($doctor)->get(route('board'))
            ->assertSee('Das Krankenhaus soll die Rettung rufen.')
            ->assertSee('bittet: Krankenhaus soll Rettung rufen')
            ->assertSee('alarm-modal__rescue--urgent', false);
        $this->getJson(route('board.live'))->assertJson(['alarm_id' => $alarm->id, 'alarm_urgent' => true, 'urgent_alarms' => 1]);
    }

    public function test_no_answer_counts_as_a_request_for_the_rescue(): void
    {
        config(['cardiopulse.sos_response_seconds' => 60]);
        $alarm = app(EmergencyService::class)->trigger($this->patient());
        $this->actingAs($this->staff());

        $this->travel(59)->seconds();
        $this->getJson(route('board.live'))->assertJson(['alarm_urgent' => false]);
        $before = $alarm->fresh()->version();

        $this->travel(2)->seconds();
        $this->getJson(route('board.live'))->assertJson(['alarm_urgent' => true, 'urgent_alarms' => 1]);
        $this->assertNotSame($before, $alarm->fresh()->version());
        $this->get(route('board'))->assertSee('Keine Rückmeldung vom Patienten')->assertSee('keine Rückmeldung');
    }

    public function test_hospital_records_that_the_rescue_was_called(): void
    {
        $weber = $this->staff();
        $krause = User::factory()->staff()->create(['username' => 't.krause', 'name' => 'Tobias Krause']);
        $patient = $this->patient();
        $alarm = app(EmergencyService::class)->trigger($patient);
        app(EmergencyService::class)->respond($patient, SosResponse::HospitalCalls);

        $this->actingAs($weber)->postJson(route('alarms.rescue', $alarm))
            ->assertOk()
            ->assertJson(['rescue_called' => true, 'rescue_called_by' => 'Dr. M. Weber']);

        $alarm->refresh();
        $this->assertSame($weber->id, $alarm->rescue_called_by);
        $this->assertSame($weber->id, $alarm->claimed_by, 'Wer die Rettung ruft, übernimmt den Alarm.');
        $this->assertFalse($alarm->needsRescueByHospital());
        $this->assertDatabaseHas('audit_logs', ['action' => 'alarm.rescue_called', 'user_id' => $weber->id]);

        // Ein Kollege überschreibt das nicht.
        $this->actingAs($krause)->postJson(route('alarms.rescue', $alarm))->assertOk()->assertJson(['rescue_called_by' => 'Dr. M. Weber']);
        $this->assertSame(1, AuditLog::query()->where('action', 'alarm.rescue_called')->count());

        $this->get(route('board'))->assertSee('Rettung verständigt</b> von Dr. M. Weber', false);
        $this->getJson(route('board.live'))->assertJson(['unclaimed_alarms' => 0, 'urgent_alarms' => 0]);

        $this->actingAs($patient->user)->getJson(route('patient.sos.status'))->assertJson(['rescue_called' => true]);
        $this->get(route('patient.sos'))
            ->assertSee('Das Krankenhaus hat die Rettung verständigt')
            ->assertDontSee('Wer ruft die Rettung?');

        $this->actingAs($weber)->postJson(route('alarms.acknowledge', $alarm))->assertOk();
        $this->get(route('audit.index'))
            ->assertSee('Rettung verständigt')
            ->assertSee('Angabe des Patienten: Krankenhaus soll rufen');
    }
}
