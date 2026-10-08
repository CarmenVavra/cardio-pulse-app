<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientLoginRequest;
use App\Models\AuditLog;
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

    public function store(PatientLoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::Patient->value,
        ];

        if (! Auth::attempt($credentials, remember: true)) {
            return back()
                ->withErrors(['email' => 'E-Mail oder Passwort ist falsch.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        AuditLog::record('auth.login', null, ['app' => 'patient']);

        return redirect()->intended(route('patient.home'));
    }
}
