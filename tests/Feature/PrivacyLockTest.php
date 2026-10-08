<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_board_masks_names_but_keeps_values(): void
    {
        $patient = $this->patient(['first_name' => 'Josef', 'last_name' => 'Brandner', 'patient_number' => 'CP-10482']);
        $this->measurement($patient, 168, 102);

        $this->actingAs($this->staff())->post('/sperre')->assertRedirect(route('board'));

        $this->get('/ueberwachung')
            ->assertOk()
            ->assertSee('Bildschirm gesperrt')
            ->assertSee('ANONYM-MODUS')
            ->assertSee('CP-10482')
            ->assertSee('168/102')
            ->assertDontSee('Josef Brandner');

        $this->getJson('/ueberwachung/live')
            ->assertJsonPath('locked', true)
            ->assertJsonPath('modal_html', null);
    }

    public function test_locked_screen_blocks_other_pages(): void
    {
        $this->actingAs($this->staff())->withSession(['screen_locked' => true]);

        $this->get('/monatsberichte')->assertRedirect(route('board'));
        $this->get('/anrufe')->assertRedirect(route('board'));
    }

    public function test_unlock_requires_correct_pin(): void
    {
        $this->actingAs($this->staff())->withSession(['screen_locked' => true]);

        $this->post('/sperre/aufheben', ['pin' => '000000'])->assertSessionHasErrors('pin');
        $this->assertTrue(session('screen_locked'));

        $this->post('/sperre/aufheben', ['pin' => '123456'])->assertRedirect(route('board'));
        $this->assertFalse(session('screen_locked'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'screen.unlocked']);
    }
}
