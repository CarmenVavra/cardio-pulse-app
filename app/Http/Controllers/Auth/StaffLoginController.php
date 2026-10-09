<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffLoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Services\StaffLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * D1 – Anmeldung & Abteilung (Krankenhaus).
 */
class StaffLoginController extends Controller
{
    public function create(): View
    {
        $departments = config('cardiopulse.departments');

        return view('auth.staff-login', compact('departments'));
    }

    public function store(StaffLoginRequest $request, LoginThrottle $throttle, StaffLoginService $logins): RedirectResponse
    {
        $username = $request->validated('username');

        // Benutzerkennungen enthalten nie ein „@“ – das ist ein Patient auf der falschen Seite.
        if (str_contains($username, '@')) {
            return back()
                ->withErrors(['username' => 'Das ist die Anmeldung für Ärzte – hier gilt die Benutzerkennung (z. B. m.weber), nicht die E-Mail-Adresse.'])
                ->with('patient_hint', true)
                ->onlyInput('department');
        }

        $throttle->ensureNotLocked($username, 'username');

        $credentials = [
            'username' => $username,
            'password' => $request->validated('password'),
            'role' => UserRole::Staff->value,
        ];

        if (! Auth::validate($credentials)) {
            $throttle->failed($username);
            AuditLog::record('auth.failed', null, ['username' => $username]);

            return back()
                ->withErrors(['username' => 'Benutzerkennung oder Passwort ist falsch.'])
                ->onlyInput('username', 'department');
        }

        $user = User::query()->where('role', UserRole::Staff)->where('username', $username)->firstOrFail();
        $department = $request->validated('department');

        // Zwei-Faktor-Anmeldung: Die Fehlversuche bleiben gezählt, bis auch der Code stimmt.
        if ($user->hasTwoFactor()) {
            $logins->startTwoFactor($request, $user, $department, $request->boolean('sound'));

            return redirect()->route('two-factor.challenge');
        }

        $throttle->succeeded($username);
        $logins->complete($request, $user, $department, $request->boolean('sound'), false);

        return redirect()->intended(route('board'));
    }
}
