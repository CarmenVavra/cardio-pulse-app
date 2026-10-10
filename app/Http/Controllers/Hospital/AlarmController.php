<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcknowledgeAlarmRequest;
use App\Http\Requests\CallRescueRequest;
use App\Http\Requests\ClaimAlarmRequest;
use App\Models\Alarm;
use App\Services\AlarmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AlarmController extends Controller
{
    /**
     * „Ich kümmere mich“ – Kollegen sehen, wer den Alarm bearbeitet.
     */
    public function claim(ClaimAlarmRequest $request, Alarm $alarm, AlarmService $alarms): JsonResponse|RedirectResponse
    {
        $alarm = $alarms->claim($alarm, $request->user());
        $mine = $alarm->claimed_by === $request->user()->id;

        if ($request->expectsJson()) {
            return response()->json(['claimed' => $mine, 'claimed_by' => $alarm->claimedBy?->shortName()]);
        }

        return redirect()->route('board')->with('status', $mine ? 'Alarm übernommen.' : 'Ein Kollege hat den Alarm bereits übernommen.');
    }

    /**
     * „Rettung verständigt“ – der Patient sieht es in der App, Kollegen rufen nicht doppelt an.
     */
    public function rescue(CallRescueRequest $request, Alarm $alarm, AlarmService $alarms): JsonResponse|RedirectResponse
    {
        $alarm = $alarms->markRescueCalled($alarm, $request->user());
        $mine = $alarm->rescue_called_by === $request->user()->id;

        if ($request->expectsJson()) {
            return response()->json(['rescue_called' => $alarm->isRescueCalled(), 'rescue_called_by' => $alarm->rescueCalledBy?->shortName()]);
        }

        return redirect()->route('board')->with('status', $mine ? 'Rettung als verständigt eingetragen.' : 'Ein Kollege hat die Rettung bereits verständigt.');
    }

    /**
     * Pflicht-Quittierung eines Alarms (wird im Audit-Log protokolliert).
     */
    public function acknowledge(AcknowledgeAlarmRequest $request, Alarm $alarm, AlarmService $alarms): JsonResponse|RedirectResponse
    {
        $alarms->acknowledge($alarm, $request->user(), $request->validated('note'));

        if ($request->expectsJson()) {
            return response()->json(['acknowledged' => true]);
        }

        return redirect()->route('board')->with('status', 'Alarm quittiert.');
    }
}
