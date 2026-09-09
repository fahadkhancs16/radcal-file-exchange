<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Friendly per-email pacing (60s between codes, with a clear message) is
        // handled in VerificationService. These HTTP limiters are only an
        // abuse backstop, and are lifted outside production so local testing
        // never hits a bare 429 page.
        RateLimiter::for('verification-send', function (Request $request) {
            if (! $this->app->isProduction()) {
                return Limit::none();
            }

            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perHour(8)->by('email:'.$email),
                Limit::perHour(40)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('verification-confirm', function (Request $request) {
            if (! $this->app->isProduction()) {
                return Limit::none();
            }

            return Limit::perMinute(15)->by($request->ip());
        });
    }
}
