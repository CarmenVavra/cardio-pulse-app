<?php

namespace App\Http\Controllers\Patient;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Http\Requests\StartPatientCallRequest;
use App\Models\Call;
use App\Services\CallService;
use App\Services\CallSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * M6 Anruf starten, M7 eingehender Anruf vom Arzt, laufendes Gespräch.
 */
class CallController extends PatientAreaController
{
    public function store(StartPatientCallRequest $request, CallService $calls): RedirectResponse
    {
        $call = $calls->callClinic($this->patient($request), $request->validated('target') === 'doctor');

        return redirect()->route('patient.calls.show', $call);
    }

    /**
     * Polling: klingelt gerade ein Anruf vom Arzt?
     */
    public function active(Request $request): JsonResponse
    {
        $call = $this->patient($request)->calls()
            ->where('direction', CallDirection::ToPatient)
            ->where('status', CallStatus::Ringing)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();

        return response()->json([
            'call' => $call ? ['id' => $call->id, 'url' => route('patient.calls.show', $call)] : null,
        ]);
    }

    public function show(Call $call, CallSignalService $signals): View
    {
        Gate::authorize('participate', $call);

        $call->load(['user', 'measurement', 'patient']);
        $iceServers = $signals->iceServers();

        return view('patient.call', compact('call', 'iceServers'));
    }

    public function status(Call $call): JsonResponse
    {
        Gate::authorize('participate', $call);

        return response()->json([
            'status' => $call->status->value,
            'elapsed' => $call->durationSeconds(),
        ]);
    }

    public function answer(Call $call, CallService $calls): RedirectResponse
    {
        Gate::authorize('participate', $call);

        $calls->answer($call);

        return redirect()->route('patient.calls.show', $call);
    }

    public function decline(Call $call, CallService $calls): RedirectResponse
    {
        Gate::authorize('participate', $call);

        $calls->decline($call);

        return redirect()->route('patient.home');
    }

    public function end(Call $call, CallService $calls): RedirectResponse
    {
        Gate::authorize('participate', $call);

        $calls->end($call);

        return redirect()->route('patient.doctor');
    }
}
