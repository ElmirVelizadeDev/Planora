<?php

namespace App\Providers;

use App\Services\Settings\SettingsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && config('queue.default') === 'sync') {
            throw new \RuntimeException('Production authentication email delivery requires a persistent queue connection.');
        }
        if (! $this->app->runningInConsole()) {
            $this->app->make(SettingsService::class)->applyRuntimeConfig();
        }
    }
}
