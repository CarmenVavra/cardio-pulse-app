<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ist die Zwei-Faktor-Anmeldung vorgeschrieben (CARDIOPULSE_REQUIRE_2FA), müssen Ärzte
 * sie einrichten, bevor sie die App nutzen. Der gesperrte (anonymisierte) Bildschirm
 * bleibt erreichbar, damit Privacy-Lock und Einrichtung sich nicht gegenseitig umleiten.
 */
class EnsureTwoFactorEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('cardiopulse.require_two_factor')
            || $user === null
            || ! $user->isStaff()
            || $user->hasTwoFactor()
            || $request->session()->get('screen_locked', false)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Zwei-Faktor-Anmeldung muss eingerichtet werden.');
        }

        return redirect()
            ->route('account.two-factor.create')
            ->with('status', 'Bitte richten Sie zuerst die Zwei-Faktor-Anmeldung ein – sie ist für alle Ärzte vorgeschrieben.');
    }
}
