<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCallNoteRequest;
use App\Models\Call;
use App\Models\Patient;
use App\Services\CallService;
use App\Services\CallSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * D5 – Telefonat Arzt ↔ Patient.
 */
class CallController extends Controller
{
    public function index(): View
    {
        $openCalls = Call::query()->open()->whereHas('patient')->with(['patient', 'user'])->latest()->get();
        $recentCalls = Call::query()
            ->whereNotIn('id', $openCalls->modelKeys())
            ->whereHas('patient')
            ->with(['patient', 'user'])
            ->latest()
            ->limit(25)
            ->get();

        return view('hospital.calls.index', compact('openCalls', 'recentCalls'));
    }

    public function store(Request $request, Patient $patient, CallService $calls): RedirectResponse
    {
        $call = $calls->callPatient($patient, $request->user());

        return redirect()->route('calls.show', $call);
    }

    public function show(Call $call, CallSignalService $signals): View
    {
        $call->load(['patient', 'user', 'measurement']);

        abort_if($call->patient === null, 404, 'Patient wurde gelöscht.');

        $todayMeasurements = $call->patient->measurements()
            ->where('measured_at', '>=', today())
            ->latest('measured_at')
            ->get();

        if ($todayMeasurements->isEmpty()) {
            $todayMeasurements = $call->patient->measurements()->latest('measured_at')->limit(4)->get();
        }

        $openAlarmCount = $call->patient->alarms()->open()->count();

        $iceServers = $signals->iceServers();

        return view('hospital.calls.show', compact('call', 'todayMeasurements', 'openAlarmCount', 'iceServers'));
    }

    public function status(Call $call): JsonResponse
    {
        return response()->json([
            'status' => $call->status->value,
            'label' => $call->status->label(),
            'elapsed' => $call->durationSeconds(),
        ]);
    }

    public function answer(Request $request, Call $call, CallService $calls): RedirectResponse
    {
        Gate::authorize('participate', $call);

        $calls->answer($call, $request->user());

        return redirect()->route('calls.show', $call);
    }

    public function end(Call $call, CallService $calls): RedirectResponse
    {
        $calls->end($call);

        return redirect()->route('calls.show', $call)->with('status', 'Gespräch beendet.');
    }

    public function note(SaveCallNoteRequest $request, Call $call, CallService $calls): RedirectResponse
    {
        $calls->saveNote($call, $request->validated('note'));

        return redirect()->route('calls.show', $call)->with('status', 'Notiz gespeichert.');
    }
}
