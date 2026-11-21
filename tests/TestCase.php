<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function grantAdminStepUp(string $purpose, ?\App\Models\User $actor = null): void
    {
        $sessionId = $this->app['session']->driver()->getId();
        $this->withSession(['admin_step_up_grant' => [
            'purpose' => $purpose,
            'expires_at' => now()->addMinute()->timestamp,
            'user_id' => (int) ($actor?->id ?? auth('sanctum')->id()),
            'session_hash' => hash('sha256', $sessionId),
        ]]);
    }
}
