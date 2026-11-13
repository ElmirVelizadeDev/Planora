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
        if (! $this->app->runningInConsole()) {
            $this->app->make(SettingsService::class)->applyRuntimeConfig();
        }
    }
}
