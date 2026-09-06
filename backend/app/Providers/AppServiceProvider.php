<?php

namespace App\Providers;

use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSenderInterface;
use App\Services\Otp\SmsOtpSender;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpSenderInterface::class, function (Application $app) {
            $driver = (string) config('otp.driver', 'log');

            return match ($driver) {
                'sms' => new SmsOtpSender($app->make(HttpFactory::class), (array) config('services.sms', [])),
                default => new LogOtpSender(),
            };
        });
    }

    public function boot(): void
    {
        // Anti-brute-force / anti-enumeration limiters, keyed by IP (+ user when present).
        RateLimiter::for('verify', fn (Request $r) => Limit::perMinute(8)->by($r->ip()));
        RateLimiter::for('otp', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('directory', fn (Request $r) => Limit::perMinute(30)
            ->by(optional($r->user())->id ?: $r->ip()));
    }
}
