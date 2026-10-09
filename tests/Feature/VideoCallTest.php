<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\CallSignal;
use App\Models\Patient;
use App\Models\User;
use App\Services\CallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VideoCallTest extends TestCase
{
    use RefreshDatabase;

    private const OFFER = ['sdp' => ['type' => 'offer', 'sdp' => "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\n"]];

    /**
     * @return array{User, Patient, Call}
     */
    private function activeCall(): array
    {
        $doctor = $this->staff();
        $patient = $this->patient(['doctor_id' => $doctor->id]);
        $calls = app(CallService::class);
        $call = $calls->answer($calls->callPatient($patient, $doctor));

        return [$doctor, $patient, $call];
    }

    public function test_active_call_shows_video_for_doctor_and_patient(): void
    {
        [$doctor, $patient, $call] = $this->activeCall();

        $this->actingAs($doctor)
            ->get(route('calls.show', $call))
            ->assertOk()
            ->assertSee('VIDEOSPRECHSTUNDE · ENDE-ZU-ENDE VERSCHLÜSSELT')
            ->assertSee('data-role="offerer"', false)
            ->assertSee('data-signal-url="'.route('calls.signals.index', $call).'"', false)
            ->assertSee('stun:stun.nextcloud.com:443');

        $this->actingAs($patient->user)
            ->get(route('patient.calls.show', $call))
            ->assertOk()
            ->assertSee('data-role="answerer"', false)
            ->assertSee('data-signal-url="'.route('patient.calls.signals.index', $call).'"', false);
    }

    public function test_ringing_call_has_no_video_yet(): void
    {
        $doctor = $this->staff();
        $call = app(CallService::class)->callPatient($this->patient(), $doctor);

        $this->actingAs($doctor)->get(route('calls.show', $call))->assertDontSee('data-video', false);
    }

    public function test_offer_and_answer_reach_only_the_other_side(): void
    {
        [$doctor, $patient, $call] = $this->activeCall();

        $offerId = $this->actingAs($doctor)
            ->postJson(route('calls.signals.store', $call), ['type' => 'offer', 'payload' => self::OFFER])
            ->assertCreated()
            ->json('id');

        $this->getJson(route('calls.signals.index', $call))->assertOk()->assertJsonCount(0, 'signals');

        $this->actingAs($patient->user)
            ->getJson(route('patient.calls.signals.index', $call))
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('signals.0.id', $offerId)
            ->assertJsonPath('signals.0.type', 'offer')
            ->assertJsonPath('signals.0.payload.sdp.type', 'offer');

        $this->postJson(route('patient.calls.signals.store', $call), [
            'type' => 'answer',
            'payload' => ['for' => $offerId, 'sdp' => ['type' => 'answer', 'sdp' => 'v=0']],
        ])->assertCreated();

        $this->actingAs($doctor)
            ->getJson(route('calls.signals.index', $call).'?after='.$offerId)
            ->assertJsonPath('signals.0.type', 'answer')
            ->assertJsonPath('signals.0.payload.for', $offerId);
    }

    /**
     * Regression: Laravel kürzte Eingaben (TrimStrings) – das Angebot verlor den letzten Zeilenumbruch
     * und der Browser lehnte es als ungültig ab („Invalid SDP line“).
     */
    public function test_session_description_is_stored_unchanged(): void
    {
        [$doctor, $patient, $call] = $this->activeCall();
        $sdp = "v=0\r\na=rtpmap:126 telephone-event/8000\r\n";

        $this->actingAs($doctor)->postJson(route('calls.signals.store', $call), [
            'type' => 'offer',
            'payload' => ['sdp' => ['type' => 'offer', 'sdp' => $sdp], 'empty' => ''],
        ])->assertCreated();

        $this->actingAs($patient->user)
            ->getJson(route('patient.calls.signals.index', $call))
            ->assertJsonPath('signals.0.payload.sdp.sdp', $sdp)
            ->assertJsonPath('signals.0.payload.empty', '');
    }

    public function test_signals_are_encrypted_and_deleted_after_hanging_up(): void
    {
        [$doctor, , $call] = $this->activeCall();
        $this->actingAs($doctor)->postJson(route('calls.signals.store', $call), [
            'type' => 'candidate',
            'payload' => ['for' => 1, 'candidate' => ['candidate' => 'candidate:1 1 udp 2122260223 192.168.1.20 54321 typ host']],
        ])->assertCreated();

        $raw = (string) DB::table('call_signals')->value('payload');
        $this->assertStringNotContainsString('192.168.1.20', $raw, 'IP-Adressen liegen nur verschlüsselt in der Datenbank.');

        app(CallService::class)->end($call);

        $this->assertSame(0, CallSignal::query()->count());
    }

    public function test_only_participants_of_an_active_call_can_signal(): void
    {
        [$doctor, , $call] = $this->activeCall();
        $stranger = $this->patient();

        $this->actingAs($stranger->user)->getJson(route('patient.calls.signals.index', $call))->assertForbidden();
        $this->actingAs($stranger->user)->postJson(route('patient.calls.signals.store', $call), ['type' => 'ready', 'payload' => []])->assertForbidden();

        $this->actingAs($doctor)->postJson(route('calls.signals.store', $call), ['type' => 'hack', 'payload' => []])->assertUnprocessable();
        $this->postJson(route('calls.signals.store', $call), ['type' => 'offer', 'payload' => ['sdp' => str_repeat('x', 25000)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload');

        app(CallService::class)->end($call);
        $this->postJson(route('calls.signals.store', $call), ['type' => 'ready', 'payload' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
        $this->getJson(route('calls.signals.index', $call))->assertJsonPath('status', 'ended');
    }

    public function test_turn_server_is_offered_when_configured(): void
    {
        config([
            'cardiopulse.video.turn_url' => 'turns:turn.example.at:5349',
            'cardiopulse.video.turn_username' => 'cardiopulse',
            'cardiopulse.video.turn_credential' => 'geheim',
        ]);
        [$doctor, , $call] = $this->activeCall();

        $this->actingAs($doctor)->get(route('calls.show', $call))->assertSee('turns:turn.example.at:5349');
    }

    public function test_camera_and_microphone_are_allowed_for_the_app_only(): void
    {
        $this->get(route('login'))->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=(), usb=(), interest-cohort=()');
    }
}
