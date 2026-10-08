<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientLoginRequest;
use App\Models\AuditLog;
use App\Services\LoginThrottle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Anmeldung in der Patienten-App.
 */
class PatientLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.patient-login');
    }

    public function store(PatientLoginRequest $request, LoginThrottle $throttle): RedirectResponse
    {
        $throttle->ensureNotLocked($request->validated('email'), 'email');

        $credentials = [
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::Patient->value,
            // Gelöschte Patienten (Soft Delete) können sich nicht mehr anmelden.
            fn (Builder $query) => $query->whereHas('patient'),
        ];

        if (! Auth::attempt($credentials, remember: true)) {
            $throttle->failed($request->validated('email'));
            AuditLog::record('auth.failed', null, ['email' => $request->validated('email')]);

            return back()
                ->withErrors(['email' => 'E-Mail oder Passwort ist falsch.'])
                ->onlyInput('email');
        }

        $throttle->succeeded($request->validated('email'));
        $request->session()->regenerate();

        AuditLog::record('auth.login', null, ['app' => 'patient']);

        return redirect()->intended(route('patient.home'));
    }
}
