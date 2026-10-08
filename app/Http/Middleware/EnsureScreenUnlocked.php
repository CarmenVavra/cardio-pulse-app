<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privacy-Lock: Solange der Bildschirm gesperrt ist, ist nur der anonymisierte Überwachungsscreen erreichbar.
 */
class EnsureScreenUnlocked
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('screen_locked', false)) {
            if ($request->expectsJson()) {
                abort(423, 'Bildschirm gesperrt.');
            }

            return redirect()->route('board');
        }

        return $next($request);
    }
}
