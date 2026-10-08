<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;

/**
 * Chat-Anweisung der Klinik an den Patienten.
 */
class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Patient $patient): RedirectResponse
    {
        $message = $patient->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        AuditLog::record('message.sent', $message, ['patient_id' => $patient->id]);

        return back()->with('status', 'Nachricht an '.$patient->fullName().' gesendet.');
    }
}
