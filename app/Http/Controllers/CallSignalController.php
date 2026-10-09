<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCallSignalRequest;
use App\Models\Call;
use App\Models\CallSignal;
use App\Services\CallSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Verbindungsaufbau der Videosprechstunde – gemeinsam für Arzt (/anrufe/…) und Patient (/app/anrufe/…).
 */
class CallSignalController extends Controller
{
    public function index(Request $request, Call $call, CallSignalService $signals): JsonResponse
    {
        Gate::authorize('participate', $call);

        $after = max(0, (int) $request->query('after', '0'));
        $messages = $signals->receive($call, $request->user(), $after);

        return response()->json([
            'status' => $call->status->value,
            'signals' => $messages->map(fn (CallSignal $signal) => [
                'id' => $signal->id,
                'type' => $signal->type,
                'payload' => $signal->payload,
            ])->values(),
        ]);
    }

    public function store(StoreCallSignalRequest $request, Call $call, CallSignalService $signals): JsonResponse
    {
        $signal = $signals->send($call, $request->user(), $request->validated('type'), $request->payload());

        return response()->json(['id' => $signal->id], 201);
    }
}
