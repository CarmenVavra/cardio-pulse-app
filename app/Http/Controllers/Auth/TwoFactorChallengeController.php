<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorChallengeRequest;
use App\Models\AuditLog;
use App\Services\LoginThrottle;
use App\Services\StaffLoginService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Zweiter Schritt der Anmeldung: Code aus der Authenticator-App oder Wiederherstellungscode.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request, StaffLoginService $logins): View|RedirectResponse
    {
        if ($logins->pending($request) === null) {
            return $this->restart();
        }

        return view('auth.staff-two-factor');
    }

    public function store(TwoFactorChallengeRequest $request, StaffLoginService $logins, TwoFactorService $twoFactor, LoginThrottle $throttle): RedirectResponse
    {
        $pending = $logins->pending($request);
        if ($pending === null) {
            return $this->restart();
        }

        $user = $pending['user'];
        $username = (string) $user->username;

        try {
            $throttle->ensureNotLocked($username, 'username');
        } catch (ValidationException $e) {
            $logins->forgetPending($request);

            return redirect()->route('login')->withErrors($e->errors());
        }

        $method = $twoFactor->verify($user, $request->validated('code'));

        if ($method === null) {
            $throttle->failed($username);
            AuditLog::record('auth.two_factor_failed', $user, [], $user);

            return back()->withErrors(['code' => 'Der Code ist nicht korrekt oder wurde bereits verwendet.']);
        }

        $throttle->succeeded($username);
        $logins->forgetPending($request);
        $logins->complete($request, $user, $pending['department'], $pending['sound'], true);

        $redirect = redirect()->intended(route('board'));
        if ($method === 'recovery') {
            $left = $twoFactor->remainingRecoveryCodes($user);
            $redirect->with('status', 'Sie haben einen Wiederherstellungscode verwendet – noch '.$left.' übrig. Neue Codes erzeugen Sie unter „Mein Konto“.');
        }

        return $redirect;
    }

    private function restart(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['username' => 'Die Anmeldung ist abgelaufen. Bitte melden Sie sich erneut an.']);
    }
}
