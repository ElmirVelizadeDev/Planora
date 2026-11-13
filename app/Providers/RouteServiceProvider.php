<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use App\Services\Settings\SettingsService;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '';
            $emailKey = hash_hmac('sha256', $email, (string) config('app.key'));
            $settings = app(SettingsService::class)->passwordPolicy();
            $attempts = max(3, min(20, (int) ($settings['max_login_attempts'] ?? 10)));
            $lockout = max(1, min(60, (int) ($settings['login_lockout_minutes'] ?? 1)));

            return [
                Limit::perMinutes($lockout, $attempts)->by('auth-login-ip:'.$request->ip()),
                Limit::perHour(30)->by('auth-login-account:'.$emailKey),
            ];
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
