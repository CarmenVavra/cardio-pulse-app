<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffLoginRequest;
use App\Models\AuditLog;
use App\Services\LoginThrottle;
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

    public function store(StaffLoginRequest $request, LoginThrottle $throttle): RedirectResponse
    {
        $throttle->ensureNotLocked($request->validated('username'), 'username');

        $credentials = [
            'username' => $request->validated('username'),
            'password' => $request->validated('password'),
            'role' => UserRole::Staff->value,
        ];

        if (! Auth::attempt($credentials)) {
            $throttle->failed($request->validated('username'));
            AuditLog::record('auth.failed', null, ['username' => $request->validated('username')]);

            return back()
                ->withErrors(['username' => 'Benutzerkennung oder Passwort ist falsch.'])
                ->onlyInput('username', 'department');
        }

        $throttle->succeeded($request->validated('username'));
        $request->session()->regenerate();

        $department = $request->validated('department');
        $request->session()->put([
            'department' => $department,
            'department_label' => config('cardiopulse.departments')[$department],
            'sound_enabled' => $request->boolean('sound'),
            'screen_locked' => false,
        ]);

        AuditLog::record('auth.login', null, ['department' => $department, 'sound' => $request->boolean('sound')]);

        return redirect()->intended(route('board'));
    }
}
