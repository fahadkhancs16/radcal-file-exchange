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
        // Sending a verification code: tight per-email, looser per-IP.
        RateLimiter::for('verification-send', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perHour(3)->by('email:'.mb_strtolower($email)),
                Limit::perHour(12)->by('ip:'.$request->ip()),
            ];
        });

        // Entering a verification code.
        RateLimiter::for('verification-confirm', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Trying an exchange password.
        RateLimiter::for('exchange-access', function (Request $request) {
            $code = (string) $request->route('code', $request->input('code'));

            return [
                Limit::perMinute(5)->by('code:'.mb_strtolower($code)),
                Limit::perHour(30)->by('ip:'.$request->ip()),
            ];
        });
    }
}
