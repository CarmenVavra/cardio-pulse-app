<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushSubscriptionRequest;
use App\Models\AuditLog;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;

/**
 * Push-Benachrichtigungen auf dem Handy des Patienten ein- und ausschalten.
 */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request, PushService $push): JsonResponse
    {
        abort_unless($push->enabled(), 404);

        $subscription = $push->subscribe(
            $request->user(),
            (string) $request->validated('endpoint'),
            (string) $request->validated('keys.p256dh'),
            (string) $request->validated('keys.auth'),
            (string) ($request->validated('contentEncoding') ?? 'aes128gcm'),
            $request->userAgent(),
        );

        if ($subscription->wasRecentlyCreated) {
            AuditLog::record('account.push_enabled', $request->user()->patient);
        }

        return response()->json(['subscribed' => true]);
    }

    public function destroy(PushSubscriptionRequest $request, PushService $push): JsonResponse
    {
        $push->unsubscribe($request->user(), (string) $request->validated('endpoint'));
        AuditLog::record('account.push_disabled', $request->user()->patient);

        return response()->json(['subscribed' => false]);
    }
}
