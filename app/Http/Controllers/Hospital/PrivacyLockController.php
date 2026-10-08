<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnlockScreenRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    public function destroy(UnlockScreenRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->pin === null || ! Hash::check($request->validated('pin'), $user->pin)) {
            AuditLog::record('screen.unlock_failed');

            return back()->withErrors(['pin' => 'PIN ist nicht korrekt.']);
        }

        $request->session()->put('screen_locked', false);

        AuditLog::record('screen.unlocked');

        return redirect()->route('board');
    }
}
