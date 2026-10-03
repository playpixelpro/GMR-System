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

        // Admin-only data cleanup (warehouses, piles, laboratory test data).
        Gate::define('manage-data-cleanup', fn (User $user) => $user->hasRole('ADMINISTRATOR'));

        // Staff may view EMR and GMR reports, but may only EXPORT AMR and PMR
        // reports. The EMR export and the printable GMR report are reserved for
        // RMEC and Administrators.
        Gate::define('export-emr-report', fn (User $user) => $user->hasRole('RMEC', 'ADMINISTRATOR'));
        Gate::define('print-gmr-report', fn (User $user) => $user->hasRole('RMEC', 'ADMINISTRATOR'));

        // Central-Office GMR approval workflow: submit, approve, and reject.
        Gate::define('manage-gmr-approvals', fn (User $user) => $user->hasRole('RMEC', 'ADMINISTRATOR'));

        // Rice-milling assignments and status changes.
        Gate::define('manage-millings', fn (User $user) => $user->hasRole('RMEC', 'ADMINISTRATOR'));

        // Logging milling accomplishment/progress per pile.
        Gate::define('record-milling-progress', fn (User $user) => $user->hasRole('STAFF', 'RMEC', 'ADMINISTRATOR'));

        // General access to Data Entry and Rice Milling modules (all non-viewer users).
        Gate::define('access-data-entry', fn (User $user) => ! $user->hasRole('VIEWER'));
        Gate::define('access-milling', fn (User $user) => ! $user->hasRole('VIEWER'));
    }
}
