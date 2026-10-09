<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Patient;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\ClinicMessageNotification;
use App\Notifications\IncomingCallNotification;
use App\Services\AppointmentService;
use App\Services\CallService;
use App\Services\MessageService;
use App\Services\PushService;
use App\Support\PushMessage;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const FCM = 'https://fcm.googleapis.com/fcm/send/geraet-1';

    private const APPLE = 'https://web.push.apple.com/geraet-2';

    // Nur für Tests erzeugtes Schlüsselpaar (nirgends sonst verwendet).
    private const TEST_PUBLIC_KEY = 'BGagXZdGUkSdNGhHWZwnozzXn_1Fg3Z0h8xI6jjZuzfyBN9T5-MmwfrHGKkDIdb38HTnGWCoddQXG4fEBOOai-g';

    private const TEST_PRIVATE_KEY = 'aPB1xqpmBDja1Qr1RSirx28CvzrKxSFMi5OH9RRtpQU';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cardiopulse.push.public_key' => self::TEST_PUBLIC_KEY,
            'cardiopulse.push.private_key' => self::TEST_PRIVATE_KEY,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(string $endpoint = self::FCM): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPublicKeyOfTheDevice', 'auth' => 'authSecret'],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    private function subscribed(Patient $patient, string $endpoint = self::FCM): PushSubscription
    {
        return app(PushService::class)->subscribe($patient->user, $endpoint, 'BPublicKeyOfTheDevice', 'authSecret', 'aes128gcm', 'Test');
    }

    public function test_patient_turns_push_on_and_off(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient->user);

        $this->get(route('patient.account.edit'))
            ->assertSee('Benachrichtigungen einschalten')
            ->assertSee('data-push-key="'.config('cardiopulse.push.public_key').'"', false);

        $this->postJson(route('patient.push.store'), $this->subscription())->assertOk()->assertJson(['subscribed' => true]);
        $this->postJson(route('patient.push.store'), $this->subscription())->assertOk(); // erneut: kein Duplikat

        $subscription = PushSubscription::query()->sole();
        $this->assertSame($patient->user_id, $subscription->user_id);
        $this->assertSame('authSecret', $subscription->auth_token);
        $this->assertStringNotContainsString('authSecret', (string) DB::table('push_subscriptions')->value('auth_token'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.push_enabled']);

        $this->deleteJson(route('patient.push.destroy'), ['endpoint' => self::FCM])->assertOk()->assertJson(['subscribed' => false]);
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.push_disabled']);
    }

    public function test_only_real_push_services_are_accepted(): void
    {
        $this->actingAs($this->patient()->user);

        foreach (['https://intern.krankenhaus.local/x', 'http://fcm.googleapis.com/fcm/send/x', 'https://fcm.googleapis.com.evil.example/x', 'https://127.0.0.1/x'] as $endpoint) {
            $this->postJson(route('patient.push.store'), $this->subscription($endpoint))->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        }

        $this->postJson(route('patient.push.store'), $this->subscription(self::APPLE))->assertOk();
        $this->postJson(route('patient.push.store'), $this->subscription('https://wns2-db5p.notify.windows.com/w/?token=x'))->assertOk();
    }

    public function test_push_is_hidden_and_closed_without_keys(): void
    {
        config(['cardiopulse.push.public_key' => null, 'cardiopulse.push.private_key' => null]);
        $patient = $this->patient();
        $this->actingAs($patient->user);

        $this->get(route('patient.account.edit'))->assertDontSee('Benachrichtigungen einschalten')->assertDontSee('data-push-key', false);
        $this->postJson(route('patient.push.store'), $this->subscription())->assertNotFound();
        $this->assertSame(0, app(PushService::class)->send($patient->user, new PushMessage('T', 'B', '/app', 't')));
    }

    public function test_staff_cannot_subscribe(): void
    {
        $this->actingAs($this->staff())->postJson(route('patient.push.store'), $this->subscription())->assertForbidden();
    }

    public function test_doctor_calling_rings_the_phone_without_health_data(): void
    {
        Notification::fake();
        $doctor = $this->staff();
        $patient = $this->patient();
        $this->subscribed($patient);

        $call = app(CallService::class)->callPatient($patient, $doctor);

        Notification::assertSentTo($patient->user, IncomingCallNotification::class, function (IncomingCallNotification $notification, array $channels) use ($patient, $call) {
            $message = $notification->toWebPush($patient->user);

            return $channels === [WebPushChannel::class]
                && $message->title === 'Dr. Miriam Weber ruft Sie an'
                && $message->url === route('patient.calls.show', $call)
                && $message->ttl === 60
                && $message->urgency === 'high'
                && $message->requireInteraction;
        });
    }

    public function test_nothing_is_pushed_to_patients_without_a_device(): void
    {
        Notification::fake();
        $patient = $this->patient();

        app(CallService::class)->callPatient($patient, $this->staff());

        // Kein Gerät angemeldet → kein Kanal → nichts verschickt.
        Notification::assertNothingSentTo($patient->user);
    }

    public function test_clinic_message_and_appointments_are_pushed(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-12 09:00'));
        $doctor = $this->staff();
        $patient = $this->patient();
        $this->subscribed($patient);

        app(MessageService::class)->sendToPatient($patient, $doctor, 'Ihr Blutdruck war gestern sehr hoch – bitte melden.');
        Notification::assertSentTo($patient->user, ClinicMessageNotification::class, function (ClinicMessageNotification $notification) use ($patient) {
            $message = $notification->toWebPush($patient->user);

            // Der Nachrichtentext erscheint nie auf dem Sperrbildschirm.
            return $message->title === 'Neue Nachricht von Ihrem Behandlungsteam'
                && ! str_contains($message->payload(), 'Blutdruck');
        });

        $appointment = app(AppointmentService::class)->schedule($patient, $doctor, Carbon::parse('2026-10-14 10:30'), 15, 'Monatsbericht besprechen', $doctor);
        Notification::assertSentTo($patient->user, AppointmentNotification::class, function (AppointmentNotification $notification, array $channels) use ($patient) {
            $message = $notification->toWebPush($patient->user);

            return $channels === ['mail', WebPushChannel::class]
                && $message->title === 'Neuer Termin: Videosprechstunde'
                && $message->body === 'Mi., 14.10. um 10:30 Uhr – Details in CardioPulse.'
                && ! str_contains($message->payload(), 'Monatsbericht');
        });

        $reminder = (new AppointmentNotification($appointment, AppointmentNotification::REMINDER))->toWebPush($patient->user);
        $this->assertSame('Videosprechstunde heute um 10:30 Uhr', $reminder->title);
    }

    public function test_service_delivers_to_every_device_and_forgets_expired_ones(): void
    {
        $patient = $this->patient();
        $phone = $this->subscribed($patient, self::FCM);
        $this->subscribed($patient, self::APPLE);

        $client = Mockery::mock(WebPush::class);
        $client->shouldReceive('queueNotification')->twice()->withArgs(function ($subscription, string $payload, array $options) {
            return json_decode($payload, true)['title'] === 'Test' && $options['TTL'] === 60 && $options['urgency'] === 'high';
        });
        $client->shouldReceive('flush')->once()->andReturnUsing(function () {
            yield new MessageSentReport(new Request('POST', self::FCM), new Response(201));
            yield new MessageSentReport(new Request('POST', self::APPLE), new Response(410), false, 'Gone');
        });

        $service = new class($client) extends PushService
        {
            public function __construct(private readonly WebPush $fake) {}

            protected function client(): WebPush
            {
                return $this->fake;
            }
        };

        $delivered = $service->send($patient->user, new PushMessage('Test', 'Text', '/app', 'call-1', 60, 'high', true));

        $this->assertSame(1, $delivered);
        $this->assertNotNull($phone->fresh()->last_used_at);
        $this->assertSame([self::FCM], PushSubscription::query()->pluck('endpoint')->all());
    }

    public function test_a_failing_push_service_never_breaks_the_call(): void
    {
        $patient = $this->patient();
        $this->subscribed($patient);

        $client = Mockery::mock(WebPush::class);
        $client->shouldReceive('queueNotification')->andThrow(new \RuntimeException('Push-Dienst nicht erreichbar'));
        $this->app->instance(PushService::class, new class($client) extends PushService
        {
            public function __construct(private readonly WebPush $fake) {}

            protected function client(): WebPush
            {
                return $this->fake;
            }
        });

        $call = app(CallService::class)->callPatient($patient, $this->staff());

        $this->assertInstanceOf(Call::class, $call);
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_vapid_keys_are_created_once(): void
    {
        if (@openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) === false) {
            $this->markTestSkipped('OpenSSL kann hier keine EC-Schlüssel erzeugen (lokal unter Windows/XAMPP ohne OPENSSL_CONF) – läuft in CI und am Server.');
        }

        $directory = sys_get_temp_dir().'/cardiopulse-vapid-'.uniqid();
        mkdir($directory);
        file_put_contents($directory.'/.env', "APP_NAME=CardioPulse\n");
        $this->app->useEnvironmentPath($directory);
        config(['cardiopulse.push.public_key' => null]);

        $this->artisan('cardiopulse:vapid-keys --write')->assertSuccessful();
        $env = (string) file_get_contents($directory.'/.env');
        $this->assertMatchesRegularExpression('/^VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{80,}$/m', $env);
        $this->assertMatchesRegularExpression('/^VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{40,}$/m', $env);
        $this->assertStringStartsWith("APP_NAME=CardioPulse\n", $env);

        // Schon eingerichtet: nichts überschreiben.
        config(['cardiopulse.push.public_key' => 'vorhanden']);
        $this->artisan('cardiopulse:vapid-keys --write')->expectsOutputToContain('bereits eingerichtet')->assertSuccessful();
        $this->assertSame($env, file_get_contents($directory.'/.env'));

        unlink($directory.'/.env');
        rmdir($directory);
    }

    public function test_service_worker_shows_and_opens_notifications(): void
    {
        $worker = (string) file_get_contents(public_path('app-sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $worker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $worker);
        $this->assertStringContainsString('showNotification', $worker);
    }

    public function test_deleting_the_user_removes_the_devices(): void
    {
        $patient = $this->patient();
        $this->subscribed($patient);

        User::query()->whereKey($patient->user_id)->forceDelete();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
