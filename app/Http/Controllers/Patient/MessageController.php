<?php

namespace App\Http\Controllers\Patient;

use App\Http\Requests\StorePatientMessageRequest;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;

/**
 * M6 – Antwort des Patienten an die Klinik.
 */
class MessageController extends PatientAreaController
{
    public function store(StorePatientMessageRequest $request, MessageService $messages): RedirectResponse
    {
        $messages->sendToClinic($this->patient($request), $request->validated('body'));

        return redirect()
            ->to(route('patient.doctor').'#nachrichten')
            ->with('status', 'Nachricht an die Klinik gesendet.');
    }
}
