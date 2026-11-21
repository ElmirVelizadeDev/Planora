<?php

namespace App\Services\Api\V1\Admin;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class AdminMfaQrCodeService
{
    public function toPngDataUri(string $provisioningUri): string
    {
        $renderer = new GDLibRenderer(256, 4, 'png', 9);
        $png = (new Writer($renderer))->writeString($provisioningUri, 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
