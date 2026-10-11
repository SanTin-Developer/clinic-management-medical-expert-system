<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // General authenticated API throttle, keyed per user (or IP when guest).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force protection for the login endpoint, keyed per email + IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->input('email').'|'.$request->ip()
            );
        });
    }
}
