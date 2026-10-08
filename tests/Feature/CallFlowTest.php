<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Models\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_calls_patient_who_answers_and_hangs_up(): void
    {
        $staff = $this->staff();
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        $this->measurement($patient, 192, 124);

        $this->actingAs($staff)->post(route('calls.store', $patient));
        $call = Call::firstOrFail();
        $this->assertSame(CallStatus::Ringing, $call->status);

        $this->get(route('calls.show', $call))
            ->assertOk()
            ->assertSee('KLINGELT BEI PATIENT')
            ->assertSee('Gesprächsnotiz');

        // Patient: Polling meldet eingehenden Anruf (M7)
        $this->actingAs($patient->user)
            ->getJson(route('patient.calls.active'))
            ->assertJsonPath('call.id', $call->id);

        $this->get(route('patient.calls.show', $call))
            ->assertOk()
            ->assertSee('EINGEHENDER ANRUF')
            ->assertSee('Dr. Miriam Weber')
            ->assertSee('192/124');

        $this->post(route('patient.calls.answer', $call));
        $this->assertSame(CallStatus::Active, $call->fresh()->status);

        $this->actingAs($staff)
            ->getJson(route('calls.status', $call))
            ->assertJsonPath('status', 'active');

        $this->put(route('calls.note', $call), ['note' => 'Amlodipin vorgezogen'])->assertRedirect();
        $this->post(route('calls.end', $call));

        $call->refresh();
        $this->assertSame(CallStatus::Ended, $call->status);
        $this->assertSame('Amlodipin vorgezogen', $call->note);
        $this->assertNotNull($call->ended_at);
    }

    public function test_patient_declines_call(): void
    {
        $patient = $this->patient();
        $this->actingAs($this->staff())->post(route('calls.store', $patient));
        $call = Call::firstOrFail();

        $this->actingAs($patient->user)->post(route('patient.calls.decline', $call))->assertRedirect(route('patient.home'));

        $this->assertSame(CallStatus::Declined, $call->fresh()->status);
    }

    public function test_patient_calls_clinic_and_staff_answers(): void
    {
        $staff = $this->staff();
        $patient = $this->patient(['doctor_id' => $staff->id, 'first_name' => 'Selin', 'last_name' => 'Aydın']);

        $this->actingAs($patient->user)
            ->post(route('patient.calls.store'), ['target' => 'doctor'])
            ->assertRedirect();
        $call = Call::firstOrFail();

        $this->actingAs($staff)
            ->getJson('/ueberwachung/live?scope=status')
            ->assertJsonPath('incoming_call.id', $call->id)
            ->assertJsonPath('incoming_call.name', 'Selin Aydın');

        $this->post(route('calls.answer', $call))->assertRedirect(route('calls.show', $call));
        $this->assertSame(CallStatus::Active, $call->fresh()->status);
    }

    public function test_patient_cannot_access_foreign_call(): void
    {
        $this->actingAs($this->staff())->post(route('calls.store', $this->patient()));

        $this->actingAs($this->patient()->user)
            ->get(route('patient.calls.show', Call::firstOrFail()))
            ->assertStatus(403);
    }

    public function test_calls_overview_lists_history(): void
    {
        $patient = $this->patient(['first_name' => 'Hans', 'last_name' => 'Becker']);
        $this->actingAs($this->staff())->post(route('calls.store', $patient));

        $this->get('/anrufe')->assertOk()->assertSee('Hans Becker')->assertSee('Klingelt');
    }
}
