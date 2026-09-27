<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        Gate::define('manage-users', fn (User $user) => $user->hasRole('ADMINISTRATOR'));

        // Staff may view EMR and GMR reports, but may only EXPORT AMR and PMR
        // reports. The EMR export and the printable GMR report are reserved for
        // RMEC and Administrators.
        Gate::define('export-emr-report', fn (User $user) => $user->hasRole('RMEC', 'ADMINISTRATOR'));
        Gate::define('print-gmr-report', fn (User $user) => $user->hasRole('RMEC', 'ADMINISTRATOR'));
    }
}
