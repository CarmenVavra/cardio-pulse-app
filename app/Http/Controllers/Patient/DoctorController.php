<?php

namespace App\Http\Controllers\Patient;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * M6 – Arzt kontaktieren, Nachrichten der Klinik.
 */
class DoctorController extends PatientAreaController
{
    public function index(Request $request): View
    {
        $patient = $this->patient($request)->load('doctor');

        $recentCalls = $patient->calls()->with('user')->latest()->limit(5)->get();
        $messages = $patient->messages()->with('user')->latest()->limit(10)->get();

        $patient->messages()->whereNull('read_at')->update(['read_at' => now()]);

        return view('patient.doctor', compact('patient', 'recentCalls', 'messages'));
    }
}
