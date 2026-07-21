<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        Password::defaults(fn () => Password::min(8));
        RateLimiter::for('public-submission', fn (Request $request) => [Limit::perMinute(5)->by($request->ip()), Limit::perDay(40)->by($request->ip())]);
        RateLimiter::for('author-revision', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('file-download', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('editorial-email', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()->id));
    }
}
