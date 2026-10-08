<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcknowledgeAlarmRequest;
use App\Models\Alarm;
use App\Services\AlarmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AlarmController extends Controller
{
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
