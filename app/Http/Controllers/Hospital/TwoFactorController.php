<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnableTwoFactorRequest;
use App\Http\Requests\ManageTwoFactorRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eigene Zwei-Faktor-Anmeldung einrichten, Wiederherstellungscodes erneuern, abschalten.
 */
class TwoFactorController extends Controller
{
    public function create(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();
        $secret = $twoFactor->pendingSecret($user);

        // SVG aus der QR-Bibliothek: enthält nur Pfade, keine Benutzereingaben.
        $qrCode = $twoFactor->qrCodeSvg($user, $secret);
        $secretKey = $twoFactor->formatSecret($secret);
        $required = (bool) config('cardiopulse.require_two_factor');
        $minutes = TwoFactorService::SETUP_MINUTES;

        return view('hospital.two-factor', compact('user', 'qrCode', 'secretKey', 'required', 'minutes'));
    }

    public function store(EnableTwoFactorRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if (! $twoFactor->hasPendingSecret($user)) {
            return redirect()->route('account.two-factor.create')->withErrors([
                'code' => 'Der QR-Code ist abgelaufen. Bitte löschen Sie den Eintrag in der App und scannen Sie den neuen QR-Code.',
            ], 'twoFactor');
        }

        if (! $twoFactor->enable($user, $request->validated('code'))) {
            return back()->withErrors([
                'code' => 'Der Code passt nicht. Bitte geben Sie den aktuell angezeigten Code ein und prüfen Sie, ob die Uhrzeit Ihres Handys stimmt.',
            ], 'twoFactor');
        }

        return redirect()->route('account.edit')->with('status', 'Die Zwei-Faktor-Anmeldung ist eingerichtet.');
    }

    public function regenerate(ManageTwoFactorRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $twoFactor->regenerateRecoveryCodes($request->user());

        return redirect()->route('account.edit')->with('status', 'Neue Wiederherstellungscodes erzeugt – die alten gelten nicht mehr.');
    }

    public function destroy(ManageTwoFactorRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $twoFactor->disable($request->user(), $request->user());

        return redirect()->route('account.edit')->with('status', 'Die Zwei-Faktor-Anmeldung ist abgeschaltet.');
    }
}
