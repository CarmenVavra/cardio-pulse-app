<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureScreenUnlocked;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'unlocked' => EnsureScreenUnlocked::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('app', 'app/*')
            ? route('patient.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isPatient()
            ? route('patient.home')
            : route('board'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
