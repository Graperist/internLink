<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
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
        // The HTE layout's topbar bell and sidebar "Notifications" link
        // both need the current unread count, and it's used on every page
        // that layout renders (dashboard, students, DTR, evaluations,
        // profile, notifications itself) - a view composer keeps that out
        // of every one of those controller methods.
        View::composer('layouts.hte', function ($view) {
            $view->with(
                'hteUnreadNotifications',
                auth()->check() ? auth()->user()->notifications_()->whereNull('read_at')->count() : 0
            );
        });
    }
}
