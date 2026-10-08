<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRangeRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\FhirExporter;
use App\Services\PatientOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * PDF- (Druckansicht) und KIS-Export (HL7 FHIR R4 Bundle).
 */
class ExportController extends Controller
{
    public function fhir(ExportRangeRequest $request, Patient $patient, FhirExporter $exporter): JsonResponse
    {
        [$from, $to] = $request->range();

        AuditLog::record('export.fhir', $patient, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        $filename = 'CardioPulse_'.$patient->patient_number.'_'.$from->format('Ymd').'-'.$to->format('Ymd').'.fhir.json';

        return response()->json(
            $exporter->bundle($patient, $from, $to),
            200,
            [
                'Content-Type' => 'application/fhir+json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function report(ExportRangeRequest $request, Patient $patient, PatientOverviewService $overviews): View
    {
        [$from, $to] = $request->range();

        $overview = $overviews->build($patient, $from, $to);
        $measurements = $patient->measurements()
            ->whereBetween('measured_at', [$from, $to])
            ->latest('measured_at')
            ->get();

        AuditLog::record('export.pdf', $patient, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return view('hospital.patients.report', compact('overview', 'measurements'));
    }
}
