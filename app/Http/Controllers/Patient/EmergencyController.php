<?php

namespace App\Http\Controllers\Patient;

use App\Http\Requests\SosRequest;
use App\Models\Alarm;
use App\Services\EmergencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notfalltaste (SOS): Taste gedrückt halten → Krankenhaus wird alarmiert, der Patient
 * ruft den Notruf an; mit Einwilligung wird der Standort mitgeschickt.
 */
class EmergencyController extends PatientAreaController
{
    public function show(Request $request, EmergencyService $emergency): View
    {
        $patient = $this->patient($request);
        $alarm = $emergency->openAlarm($patient);
        $handled = $alarm === null ? $emergency->recentlyHandled($patient) : null;

        return view('patient.sos', compact('patient', 'alarm', 'handled'));
    }

    public function store(SosRequest $request, EmergencyService $emergency): JsonResponse|RedirectResponse
    {
        $alarm = $emergency->trigger($this->patient($request), $request->location());

        if ($request->expectsJson()) {
            return response()->json($this->state($alarm));
        }

        return redirect()->route('patient.sos');
    }

    public function location(SosRequest $request, EmergencyService $emergency): JsonResponse
    {
        $location = $request->location();
        $alarm = $location !== null ? $emergency->updateLocation($this->patient($request), $location) : null;

        return response()->json($this->state($alarm));
    }

    public function falseAlarm(Request $request, EmergencyService $emergency): RedirectResponse
    {
        $emergency->markFalseAlarm($this->patient($request));

        return redirect()->route('patient.sos');
    }

    /**
     * Für die Live-Anzeige in der App: alarmiert, übernommen, bearbeitet.
     */
    public function status(Request $request, EmergencyService $emergency): JsonResponse
    {
        $patient = $this->patient($request);
        $alarm = $emergency->openAlarm($patient);

        return response()->json($this->state($alarm) + [
            'handled' => $alarm === null && $emergency->recentlyHandled($patient) !== null,
        ]);
    }

    /**
     * @return array{open: bool, claimed_by: string|null, located: bool}
     */
    private function state(?Alarm $alarm): array
    {
        $alarm?->loadMissing('claimedBy');

        return [
            'open' => $alarm?->isOpen() ?? false,
            'claimed_by' => $alarm?->claimedBy?->displayName(),
            'located' => $alarm?->location !== null,
        ];
    }
}
