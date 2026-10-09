<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePinRequest;
use App\Services\AccountService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eigenes Konto des Arztes: Passwort, Privacy-Lock-PIN und Überblick zur Zwei-Faktor-Anmeldung.
 */
class AccountController extends Controller
{
    public function edit(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();
        $recoveryCodesLeft = $twoFactor->remainingRecoveryCodes($user);
        $twoFactorRequired = (bool) config('cardiopulse.require_two_factor');

        return view('hospital.account', compact('user', 'recoveryCodesLeft', 'twoFactorRequired'));
    }

    public function updatePassword(UpdatePasswordRequest $request, AccountService $accounts): RedirectResponse
    {
        $accounts->changePassword($request->user(), $request->validated('password'), $request->session()->getId());

        return redirect()->route('account.edit')->with('status', 'Ihr Passwort wurde geändert. Andere Sitzungen wurden abgemeldet.');
    }

    public function updatePin(UpdatePinRequest $request, AccountService $accounts): RedirectResponse
    {
        $accounts->changePin($request->user(), $request->validated('pin'));

        return redirect()->route('account.edit')->with('status', 'Ihre PIN wurde geändert.');
    }
}
