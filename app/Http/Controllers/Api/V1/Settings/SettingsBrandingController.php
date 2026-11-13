<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;

class SettingsBrandingController extends Controller
{
    public function show(SettingsService $settings): JsonResponse
    {
        $general = $settings->publicValues();
        return response()->json(['data' => [
            'application_name' => $general['application_name'] ?? config('app.name'),
            'workspace_name' => $general['workspace_name'] ?? 'Planora Workspace',
            'application_logo_url' => $general['application_logo_url'] ?? null,
        ]]);
    }
}
