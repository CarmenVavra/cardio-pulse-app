<?php

namespace Tests\Feature;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_replies_to_clinic(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)
            ->post(route('patient.messages.store'), ['body' => '  Soll ich die Tablette abends nehmen?  '])
            ->assertRedirect(route('patient.doctor').'#nachrichten')
            ->assertSessionHas('status', 'Nachricht an die Klinik gesendet.');

        $message = Message::query()->sole();
        $this->assertTrue($message->from_patient);
        $this->assertSame($patient->user_id, $message->user_id);
        $this->assertSame('Soll ich die Tablette abends nehmen?', $message->body);
        $this->assertNull($message->read_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'message.received', 'user_id' => $patient->user_id]);

        $this->get(route('patient.doctor'))
            ->assertOk()
            ->assertSee('Soll ich die Tablette abends nehmen?')
            ->assertSee('Gesendet');
    }

    public function test_patient_message_validation(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient->user)
            ->post(route('patient.messages.store'), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->post(route('patient.messages.store'), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::query()->count());
    }

    public function test_clinic_sees_reply_on_board_and_in_detail(): void
    {
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner']);
        $this->measurement($patient, 128, 82);
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('patients.messages.store', $patient), ['body' => 'Bitte morgen erneut messen.']);
        $this->actingAs($patient->user)->post(route('patient.messages.store'), ['body' => 'Mache ich gerne.']);

        $this->actingAs($staff)
            ->get(route('board'))
            ->assertOk()
            ->assertSeeText('1 neue Nachricht von Josef Brandner');

        $this->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSeeInOrder(['Nachrichten', 'Bitte morgen erneut messen.', 'Neu', 'Mache ich gerne.'])
            ->assertSee('Nachricht an Josef Brandner');

        $this->assertNotNull(Message::query()->fromPatient()->sole()->read_at);
        $this->get(route('board'))->assertDontSeeText('neue Nachricht von Josef Brandner');

        $this->actingAs($patient->user)
            ->get(route('patient.doctor'))
            ->assertSee('Gelesen');
    }

    public function test_patient_reads_only_clinic_messages_and_own_messages_do_not_count_as_unread(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient->user)->post(route('patient.messages.store'), ['body' => 'Frage zur Medikation']);

        $this->get('/app')->assertOk()->assertDontSee('ungelesen');

        $this->get(route('patient.doctor'))->assertOk();

        $this->assertNull(Message::query()->fromPatient()->sole()->read_at);
    }

    public function test_staff_reply_redirects_back_to_conversation(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->staff())
            ->from(route('patients.show', $patient))
            ->post(route('patients.messages.store', $patient), ['body' => 'Alles in Ordnung.'])
            ->assertRedirect(route('patients.show', $patient).'#nachrichten');

        $this->assertFalse(Message::query()->sole()->from_patient);
    }

    public function test_conversations_are_separated_by_patient(): void
    {
        $josef = $this->patient(['first_name' => 'Josef']);
        $karin = $this->patient(['first_name' => 'Karin']);

        $this->actingAs($karin->user)->post(route('patient.messages.store'), ['body' => 'Nachricht von Karin']);

        $this->actingAs($josef->user)
            ->get(route('patient.doctor'))
            ->assertDontSee('Nachricht von Karin');

        $this->assertSame($karin->id, Message::query()->sole()->patient_id);
    }

    public function test_staff_cannot_use_patient_message_route(): void
    {
        $this->actingAs($this->staff())
            ->post(route('patient.messages.store'), ['body' => 'Test'])
            ->assertStatus(403);

        $this->assertSame(0, Message::query()->count());
    }
}
