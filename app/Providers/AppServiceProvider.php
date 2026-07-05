<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    /**
     * Configure named rate limiters used by queued jobs.
     */
    protected function configureRateLimiters(): void
    {
        // Cap outbound product scrapes so the nightly batch stays polite to
        // retailer sites and bounds ScrapingBee spend. Limited jobs are
        // released and retried, not dropped.
        RateLimiter::for('price-checks', fn (): Limit => Limit::perMinute(20));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Behind Ploi's TLS termination the app can see requests as plain HTTP.
        // Force HTTPS so every generated URL — including invite links built in
        // the queue worker, where there is no request scheme — stays secure.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Surface N+1 regressions during development; never break production.
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
