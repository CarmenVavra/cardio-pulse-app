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
    /** Der neue Schlüssel wird erst nach dem ersten richtigen Code beim Benutzer gespeichert. */
    private const SETUP_SECRET = 'two_factor.setup_secret';

    public function create(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();

        $secret = $request->session()->get(self::SETUP_SECRET);
        if (! is_string($secret)) {
            $secret = $twoFactor->generateSecret();
            $request->session()->put(self::SETUP_SECRET, $secret);
        }

        // SVG aus der QR-Bibliothek: enthält nur Pfade, keine Benutzereingaben.
        $qrCode = $twoFactor->qrCodeSvg($user, $secret);
        $secretKey = $twoFactor->formatSecret($secret);
        $required = (bool) config('cardiopulse.require_two_factor');

        return view('hospital.two-factor', compact('user', 'qrCode', 'secretKey', 'required'));
    }

    public function store(EnableTwoFactorRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $secret = $request->session()->get(self::SETUP_SECRET);
        if (! is_string($secret)) {
            return redirect()->route('account.two-factor.create');
        }

        $codes = $twoFactor->enable($request->user(), $secret, $request->validated('code'));
        if ($codes === null) {
            return back()->withErrors([
                'code' => 'Der Code passt nicht. Bitte geben Sie den aktuell angezeigten Code ein und prüfen Sie, ob die Uhrzeit Ihres Handys stimmt.',
            ], 'twoFactor');
        }

        $request->session()->forget(self::SETUP_SECRET);

        return redirect()
            ->route('account.edit')
            ->with('status', 'Die Zwei-Faktor-Anmeldung ist eingerichtet.')
            ->with('recovery_codes', $codes);
    }

    public function regenerate(ManageTwoFactorRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $codes = $twoFactor->regenerateRecoveryCodes($request->user());

        return redirect()
            ->route('account.edit')
            ->with('status', 'Neue Wiederherstellungscodes erzeugt – die alten gelten nicht mehr.')
            ->with('recovery_codes', $codes);
    }

    public function destroy(ManageTwoFactorRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $twoFactor->disable($request->user(), $request->user());

        return redirect()->route('account.edit')->with('status', 'Die Zwei-Faktor-Anmeldung ist abgeschaltet.');
    }
}
