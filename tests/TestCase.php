<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private ?array $adminStepUpRequestDefaults = null;

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
}
