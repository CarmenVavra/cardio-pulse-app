<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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

        // {doctor} in Routen löst nur aktive Ärzte (Personal) auf.
        Route::bind('doctor', fn (string $value) => User::query()
            ->where('role', UserRole::Staff)
            ->findOrFail($value));

        Carbon::setLocale('de');
    }
}
