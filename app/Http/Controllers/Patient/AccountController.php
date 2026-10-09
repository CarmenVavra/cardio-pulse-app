<?php

namespace App\Http\Controllers\Patient;

use App\Http\Requests\LocationConsentRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AccountService;
use App\Services\EmergencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eigenes Konto des Patienten: Passwort ändern, Standort im Notfall freigeben.
 */
class AccountController extends PatientAreaController
{
    public function edit(Request $request): View
    {
        $patient = $this->patient($request)->load('user');

        return view('patient.account', compact('patient'));
    }

    public function updatePassword(UpdatePasswordRequest $request, AccountService $accounts): RedirectResponse
    {
        $accounts->changePassword($request->user(), $request->validated('password'), $request->session()->getId());

        return redirect()->route('patient.account.edit')->with('status', 'Ihr Passwort wurde geändert. Andere Geräte wurden abgemeldet.');
    }

    public function updateLocationConsent(LocationConsentRequest $request, EmergencyService $emergency): RedirectResponse
    {
        $emergency->setLocationConsent($this->patient($request), $request->consent());

        $status = $request->consent()
            ? 'Danke. Bei einem Notruf über die Notfalltaste wird Ihr Standort an das Krankenhaus übermittelt.'
            : 'Ihr Standort wird nicht mehr übermittelt.';

        $back = $request->fromSosPage() ? route('patient.sos') : route('patient.account.edit').'#standort';

        return redirect()->to($back)->with('status', $status);
    }
}
