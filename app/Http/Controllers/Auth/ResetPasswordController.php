<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Neues Passwort über den Link aus der E-Mail festlegen.
 */
class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $patient = $request->routeIs('patient.*');

        return view($patient ? 'auth.patient-reset-password' : 'auth.staff-reset-password', compact('token'));
    }

    public function store(ResetPasswordRequest $request, PasswordResetService $resets): RedirectResponse
    {
        $status = $resets->reset($request->credentials());

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route($request->routeIs('patient.*') ? 'patient.login' : 'login')
            ->with('status', __($status));
    }
}
