<?php

namespace App\Http\Controllers\Patient;

use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eigenes Konto des Patienten: Passwort ändern.
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
}
