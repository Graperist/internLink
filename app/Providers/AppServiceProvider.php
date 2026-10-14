<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // The app uses Bootstrap 5; without this, ->links() renders Laravel's
        // Tailwind pagination (oversized arrow icons, unstyled buttons).
        Paginator::useBootstrapFive();
    }
}
