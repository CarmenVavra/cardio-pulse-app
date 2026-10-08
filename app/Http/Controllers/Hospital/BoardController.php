<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Services\BoardSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * D2 – Überwachungsscreen (Live-Board), D3 – Alarm, D6 – Privacy-Lock.
 */
class BoardController extends Controller
{
    public function index(Request $request, BoardSnapshot $snapshot): View
    {
        ['rows' => $rows, 'counts' => $counts, 'uploadsToday' => $uploadsToday, 'patientsTotal' => $patientsTotal] = $snapshot->board();
        ['openAlarms' => $openAlarms, 'acknowledged' => $acknowledged] = $snapshot->alarms();
        $locked = (bool) $request->session()->get('screen_locked', false);
        $demo = (bool) config('cardiopulse.demo');

        return view('hospital.board', compact('rows', 'counts', 'uploadsToday', 'patientsTotal', 'openAlarms', 'acknowledged', 'locked', 'demo'));
    }

    /**
     * Live-Aktualisierung (Polling). Bei gesperrtem Bildschirm werden keine Namen ausgeliefert.
     */
    public function live(Request $request, BoardSnapshot $snapshot): JsonResponse
    {
        $locked = (bool) $request->session()->get('screen_locked', false);
        ['openAlarms' => $openAlarms, 'acknowledged' => $acknowledged] = $snapshot->alarms();
        $firstAlarm = $openAlarms->first();
        $incoming = $locked ? null : $snapshot->incomingCall();

        $payload = [
            'locked' => $locked,
            'open_alarms' => $openAlarms->count(),
            'alarm_id' => $firstAlarm?->id,
            'banner_html' => view('hospital.partials.alarm-banner', compact('openAlarms', 'acknowledged', 'locked'))->render(),
            'modal_html' => $firstAlarm && ! $locked
                ? view('hospital.partials.alarm-modal', ['alarm' => $firstAlarm, 'more' => $openAlarms->count() - 1])->render()
                : null,
            'incoming_call' => $incoming ? [
                'id' => $incoming->id,
                'name' => $incoming->patient->fullName(),
                'answer_url' => route('calls.answer', $incoming),
            ] : null,
        ];

        if ($request->query('scope') !== 'status') {
            ['rows' => $rows, 'counts' => $counts, 'uploadsToday' => $uploadsToday, 'patientsTotal' => $patientsTotal] = $snapshot->board();

            $payload += [
                'rows_html' => view('hospital.partials.board-rows', compact('rows', 'locked'))->render(),
                'counts' => $counts,
                'uploads_today' => $uploadsToday,
                'patients_total' => $patientsTotal,
            ];
        }

        return response()->json($payload);
    }
}
