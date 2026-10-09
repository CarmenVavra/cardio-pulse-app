<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * „Passwort vergessen“ für Ärzte (/passwort-vergessen) und Patienten (/app/passwort-vergessen).
 */
class ForgotPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view($request->routeIs('patient.*') ? 'auth.patient-forgot-password' : 'auth.staff-forgot-password');
    }

    public function store(ForgotPasswordRequest $request, PasswordResetService $resets): RedirectResponse
    {
        $resets->sendLink($request->validated('email'));

        // Immer dieselbe Antwort – unabhängig davon, ob es das Konto gibt.
        return back()->with('status', __('passwords.sent'));
    }
}
