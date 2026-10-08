<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Patient;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;

/**
 * Chat-Nachricht der Klinik an den Patienten.
 */
class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Patient $patient, MessageService $messages): RedirectResponse
    {
        $messages->sendToPatient($patient, $request->user(), $request->validated('body'));

        return redirect()
            ->to(url()->previous().'#nachrichten')
            ->with('status', 'Nachricht an '.$patient->fullName().' gesendet.');
    }
}
