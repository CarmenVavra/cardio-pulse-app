<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Einheitliche Passwortregel für Ärzte und Patienten (Anlegen, Bearbeiten, eigenes Konto).
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Im Produktivbetrieb ausschließlich HTTPS (Gesundheitsdaten, MDCG 2019-16).
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Reverse-Proxy beim Hosting (TRUSTED_PROXIES): nur dann X-Forwarded-Header für HTTPS und Client-IP auswerten.
        $proxies = config('cardiopulse.trusted_proxies');
        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // {doctor} in Routen löst nur aktive Ärzte (Personal) auf.
        Route::bind('doctor', fn (string $value) => User::query()
            ->where('role', UserRole::Staff)
            ->findOrFail($value));

        Carbon::setLocale('de');
    }
}
