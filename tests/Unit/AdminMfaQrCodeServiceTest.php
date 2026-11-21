<?php

namespace Tests\Unit;

use App\Services\Api\V1\Admin\AdminMfaQrCodeService;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use PHPUnit\Framework\TestCase;

class AdminMfaQrCodeServiceTest extends TestCase
{
    public function test_totp_provisioning_uri_is_rendered_as_a_valid_local_png_with_exact_payload(): void
    {
        $uri = 'otpauth://totp/Planora%3Atest%40example.test?secret=JBSWY3DPEHPK3PXP&issuer=Planora&algorithm=SHA1&digits=6&period=30';
        $service = new AdminMfaQrCodeService();

        $dataUri = $service->toPngDataUri($uri);

        self::assertStringStartsWith('data:image/png;base64,', $dataUri);
        $png = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
        self::assertNotFalse($png);
        self::assertSame(IMAGETYPE_PNG, getimagesizefromstring($png)[2]);
        self::assertSame(
            'data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(256, 4, 'png', 9)))
                ->writeString($uri, 'UTF-8', ErrorCorrectionLevel::M())),
            $dataUri,
            'The QR image must be generated from the original provisioning URI without changing its payload.'
        );
    }
}
