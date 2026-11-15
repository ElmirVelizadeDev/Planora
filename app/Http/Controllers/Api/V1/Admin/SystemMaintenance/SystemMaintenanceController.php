<?php

namespace App\Http\Controllers\Api\V1\Admin\SystemMaintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SystemMaintenance\EnableMaintenanceRequest;
use App\Services\Api\V1\Admin\SystemMaintenance\SystemMaintenanceService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SystemMaintenanceController extends Controller
{
    public function show(SystemMaintenanceService $maintenance): JsonResponse
    {
        return response()->json(['data' => $maintenance->status()]);
    }

    public function enable(EnableMaintenanceRequest $request, SystemMaintenanceService $maintenance): JsonResponse
    {
        if ($maintenance->status()['active']) {
            return response()->json(['message' => 'Maintenance mode is already active.'], 409);
        }

        try {
            $status = $maintenance->enable($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Maintenance mode could not be enabled. Use the server maintenance command if the problem continues.'], 500);
        }

        return response()->json([
            'message' => 'Maintenance mode is active. The control page remains available to administrators.',
            'data' => $status,
        ]);
    }

    public function disable(SystemMaintenanceService $maintenance): JsonResponse
    {
        try {
            $wasActive = $maintenance->status()['active'];
            $status = $maintenance->disable();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Maintenance mode could not be disabled. Use the server maintenance command if the problem continues.'], 500);
        }

        return response()->json([
            'message' => $wasActive ? 'Maintenance mode is disabled. Planora is available again.' : 'Maintenance mode was already inactive.',
            'data' => $status,
        ]);
    }
}
