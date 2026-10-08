<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

abstract class PatientAreaController extends Controller
{
    /**
     * Der Patient des angemeldeten App-Benutzers.
     */
    protected function patient(Request $request): Patient
    {
        $patient = $request->user()?->patient;

        abort_if($patient === null, 403, 'Kein Patientenprofil vorhanden.');

        return $patient;
    }
}
