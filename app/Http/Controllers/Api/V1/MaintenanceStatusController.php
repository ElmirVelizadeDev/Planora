<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Http\JsonResponse;

class MaintenanceStatusController extends Controller
{
    public function show(MaintenanceScopeStore $maintenance): JsonResponse
    {
        $state = $maintenance->read();

        return response()->json([
            'data' => [
                'scopes' => $state['scopes'],
                'message' => $state['message'],
                'estimated_at' => $state['estimated_at'],
                'retry_after' => $state['retry_after'],
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
