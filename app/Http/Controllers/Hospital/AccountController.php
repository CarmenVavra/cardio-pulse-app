<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePinRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eigenes Konto des Arztes: Passwort und Privacy-Lock-PIN.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('hospital.account', compact('user'));
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
