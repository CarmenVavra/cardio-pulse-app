<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC: Krankenhaus-Bereich nur für Personal, App-Bereich nur für Patienten.
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user === null || $user->role->value !== $role) {
            abort(403, 'Kein Zugriff auf diesen Bereich.');
        }

        return $next($request);
    }
}
