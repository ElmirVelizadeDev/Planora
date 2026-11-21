<?php

namespace Tests\Unit;

use App\Services\Api\V1\Admin\AdminMfaService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AdminMfaTotpTest extends TestCase
{
    public function test_totp_implementation_matches_rfc_6238_sha1_six_digit_vector(): void
    {
        Carbon::setTestNow(Carbon::createFromTimestampUTC(59));
        try {
            $service = new AdminMfaService();
            $method = new ReflectionMethod($service, 'matchingCounter');
            $counter = $method->invoke($service, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082');
            $this->assertSame(1, $counter);
            $this->assertNull($method->invoke($service, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
