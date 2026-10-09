<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallableAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_describes_the_patient_app(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('CardioPulse', $manifest['name']);
        $this->assertSame('/app', $manifest['start_url']);
        $this->assertSame('/app', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('de', $manifest['lang']);

        // Android verlangt Symbole in 192 und 512 Pixeln, eines davon zuschneidbar (maskable).
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file);
            [$width, $height] = getimagesize($file) ?: [0, 0];
            $this->assertSame($icon['sizes'], $width.'x'.$height);
        }
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('icons/apple-touch-icon.png')) ?: [], 0, 2));
    }

    public function test_patient_pages_are_installable_but_hospital_pages_are_not(): void
    {
        $this->get(route('patient.login'))
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-touch-icon.png', false);

        $patient = $this->patient();
        $this->actingAs($patient->user)->get(route('patient.home'))
            ->assertSee('rel="manifest"', false)
            ->assertSee('CardioPulse als App')
            ->assertSee('data-install-dismissible', false);
        $this->get(route('patient.account.edit'))
            ->assertSee('CardioPulse als App')
            ->assertSee('Zum Home-Bildschirm');

        $this->actingAs($this->staff())->get(route('board'))->assertDontSee('rel="manifest"', false);
    }

    public function test_offline_page_works_without_login_and_shows_the_emergency_number(): void
    {
        $this->get(route('patient.offline'))
            ->assertOk()
            ->assertSee('Keine Internetverbindung')
            ->assertSee('tel:144')
            ->assertDontSee('/build/', false); // eigenständig, damit sie offline vollständig ist

        $patient = $this->patient();
        $this->actingAs($patient->user)->get(route('patient.offline'))->assertOk();
    }

    public function test_service_worker_only_caches_the_offline_page(): void
    {
        $worker = (string) file_get_contents(public_path('app-sw.js'));

        $this->assertStringContainsString("const OFFLINE_URL = '/app/offline';", $worker);
        $this->assertStringContainsString("event.request.mode !== 'navigate'", $worker);
        // Keine Gesundheitsdaten im Zwischenspeicher: nur die Offline-Seite wird abgelegt.
        $this->assertSame(1, substr_count($worker, 'cache.add('));
        $this->assertStringNotContainsString('cache.put(', $worker);
    }
}
