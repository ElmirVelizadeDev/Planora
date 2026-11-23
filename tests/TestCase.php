<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private ?array $adminStepUpRequestDefaults = null;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->environment('testing')) {
            app(MaintenanceScopeStore::class)->reset();
            if ($this->app->isDownForMaintenance()) Artisan::call('up');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->app) && $this->app->environment('testing')) {
            app(MaintenanceScopeStore::class)->disableAll();
            if ($this->app->isDownForMaintenance()) Artisan::call('up');
        }

        parent::tearDown();
    }

    /** The shared API fixture represents an administrator who completed mandatory MFA. */
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($user instanceof User && $user->role === 'admin' && ! $user->mfa_enabled_at) {
            $user->forceFill([
                'mfa_secret' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
                'mfa_enabled_at' => now(),
            ])->save();
        }

        if ($user instanceof User && $user->role === 'admin') Auth::guard('web')->setUser($user);
        elseif ($guard !== 'web') Auth::guard('web')->forgetUser();

        $result = parent::actingAs($user, $guard);
        if ($guard === 'web' || ($user instanceof User && $user->role === 'admin')) {
            $this->withSession(['auth_version_web' => (int) ($user->auth_version ?? 1)]);
        }
        return $result;
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $defaults = $this->adminStepUpRequestDefaults;

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            if ($defaults !== null) {
                $this->defaultHeaders = $defaults['headers'];
                $this->defaultCookies = $defaults['cookies'];
                $this->withCredentials = $defaults['with_credentials'];
                $this->adminStepUpRequestDefaults = null;
            }
        }
    }

    protected function grantAdminStepUp(string $purpose, ?\App\Models\User $actor = null): void
    {
        $session = $this->app['session']->driver();
        $session->start();
        $session->regenerateToken();
        $sessionId = $session->getId();
        $csrfToken = $session->token();

        $this->adminStepUpRequestDefaults = [
            'headers' => $this->defaultHeaders,
            'cookies' => $this->defaultCookies,
            'with_credentials' => $this->withCredentials,
        ];
        $this->withHeaders([
            'Origin' => 'http://planora.test',
            'Referer' => 'http://planora.test/',
            'X-XSRF-TOKEN' => $csrfToken,
        ]);
        $this->withCredentials()->withCookie(config('session.cookie'), $sessionId);
        $this->withSession(['admin_step_up_grant' => [
            'purpose' => $purpose,
            'expires_at' => now()->addMinute()->timestamp,
            'user_id' => (int) ($actor?->id ?? auth('sanctum')->id()),
            'session_hash' => hash('sha256', $sessionId),
        ]]);
    }

    protected function currentTotpCode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $char) $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        $key = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $key .= chr(bindec($byte));
        $counter = intdiv(now()->timestamp, 30);
        $digest = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($digest[19]) & 0x0f;
        $number = ((ord($digest[$offset]) & 0x7f) << 24) | ((ord($digest[$offset + 1]) & 0xff) << 16)
            | ((ord($digest[$offset + 2]) & 0xff) << 8) | (ord($digest[$offset + 3]) & 0xff);
        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
