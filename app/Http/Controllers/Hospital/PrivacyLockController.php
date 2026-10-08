<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnlockScreenRequest;
use App\Models\AuditLog;
use App\Services\PinCheck;
use App\Services\PrivacyLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * D6 – Privacy-Lock (Anonym-Modus nach Inaktivität).
 */
class PrivacyLockController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->session()->put('screen_locked', true);

        AuditLog::record('screen.locked');

        if ($request->expectsJson()) {
            return response()->json(['locked' => true]);
        }

        return redirect()->route('board');
    }

    public function destroy(UnlockScreenRequest $request, PrivacyLockService $lock): RedirectResponse
    {
        $user = $request->user();
        $result = $lock->attemptUnlock($user, $request->validated('pin'));

        if ($result === PinCheck::LockedOut) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Zu viele falsche PIN-Eingaben. Sie wurden aus Sicherheitsgründen abgemeldet.']);
        }

        if ($result === PinCheck::Invalid) {
            $remaining = $lock->remainingAttempts($user);

            return back()->withErrors(['pin' => 'PIN ist nicht korrekt. Noch '.$remaining.' '.($remaining === 1 ? 'Versuch' : 'Versuche').', danach werden Sie abgemeldet.']);
        }

        $request->session()->put('screen_locked', false);

        return redirect()->route('board');
    }
}
