<?php

namespace App\Http\Controllers\Patient;

use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * M6 – Arzt kontaktieren, Nachrichten mit der Klinik.
 */
class DoctorController extends PatientAreaController
{
    public function index(Request $request, MessageService $messageService): View
    {
        $patient = $this->patient($request)->load('doctor');

        $recentCalls = $patient->calls()->with('user')->latest()->limit(5)->get();
        $messages = $messageService->thread($patient);

        $messageService->markReadByPatient($patient);

        return view('patient.doctor', compact('patient', 'recentCalls', 'messages'));
    }
}
