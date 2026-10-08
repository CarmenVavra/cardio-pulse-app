<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\MeasurementRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Demo-Modus: simuliert einen Upload aus einer Patienten-App.
 */
class DemoUploadController extends Controller
{
    public function __invoke(Request $request, MeasurementRecorder $recorder): JsonResponse|RedirectResponse
    {
        abort_unless(config('cardiopulse.demo'), 404);

        $patient = Patient::query()->inRandomOrder()->firstOrFail();

        [$systolic, $diastolic] = match (random_int(1, 10)) {
            1, 2 => [random_int(181, 205), random_int(105, 128)],
            3, 4, 5 => [random_int(131, 172), random_int(82, 108)],
            6 => [random_int(78, 88), random_int(50, 58)],
            default => [random_int(110, 128), random_int(68, 82)],
        };

        $recorder->record($patient, [
            'systolic' => $systolic,
            'diastolic' => max(40, min($diastolic, $systolic - 20)),
            'pulse' => random_int(58, 104),
            'method' => 'bluetooth',
            'symptoms' => $systolic >= 180 ? ['kopfschmerz'] : [],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('board');
    }
}
