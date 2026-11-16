<?php

namespace App\Http\Controllers\Api\V1\Admin\SystemMaintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SystemMaintenance\EnableMaintenanceRequest;
use App\Http\Requests\Api\V1\Admin\SystemMaintenance\UpdateMaintenanceScopeRequest;
use App\Http\Requests\Api\V1\Admin\SystemMaintenance\UpdateMaintenancePreferencesRequest;
use App\Services\Api\V1\Admin\SystemMaintenance\SystemMaintenanceService;
use App\Services\Api\V1\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SystemMaintenanceController extends Controller
{
    public function show(SystemMaintenanceService $maintenance): JsonResponse
    {
        return response()->json(['data' => $maintenance->status()]);
    }

    public function enable(EnableMaintenanceRequest $request, SystemMaintenanceService $maintenance, NotificationService $notifications): JsonResponse
    {
        try {
            $before = $maintenance->status();
            $status = $maintenance->enable($request->validated());
        } catch (Throwable $exception) {
            if ($exception instanceof \RuntimeException) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            report($exception);
            return response()->json(['message' => 'Maintenance mode could not be enabled. Use the server maintenance command if the problem continues.'], 500);
        }

        $this->notifyIfChanged($notifications, $request->user(), $before, $status);

        return response()->json([
            'message' => 'All application maintenance scopes are active.',
            'data' => $status,
        ]);
    }

    public function updateScope(UpdateMaintenanceScopeRequest $request, string $scope, SystemMaintenanceService $maintenance, NotificationService $notifications): JsonResponse
    {
        try {
            $before = $maintenance->status();
            $status = $maintenance->updateScope($request->scopeKey(), $request->validated());
        } catch (Throwable $exception) {
            if ($exception instanceof \RuntimeException) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            report($exception);
            return response()->json(['message' => 'This maintenance scope could not be changed. Please retry.'], 500);
        }

        $this->notifyIfChanged($notifications, $request->user(), $before, $status);

        return response()->json([
            'message' => $request->validated('enabled')
                ? 'The selected maintenance scope is active.'
                : 'The selected maintenance scope is available again.',
            'data' => $status,
        ]);
    }

    public function updatePreferences(UpdateMaintenancePreferencesRequest $request, SystemMaintenanceService $maintenance, NotificationService $notifications): JsonResponse
    {
        try {
            $before = $maintenance->status();
            $status = $maintenance->updatePreferences($request->validated());
        } catch (Throwable $exception) {
            if ($exception instanceof \RuntimeException) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            report($exception);
            return response()->json(['message' => 'Maintenance message settings could not be saved. Please retry.'], 500);
        }

        $this->notifyIfChanged($notifications, $request->user(), $before, $status);

        return response()->json([
            'message' => 'Maintenance message settings were saved.',
            'data' => $status,
        ]);
    }

    public function disable(\Illuminate\Http\Request $request, SystemMaintenanceService $maintenance, NotificationService $notifications): JsonResponse
    {
        try {
            $before = $maintenance->status();
            $status = $maintenance->disable();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Maintenance mode could not be disabled. Use the server maintenance command if the problem continues.'], 500);
        }

        $this->notifyIfChanged($notifications, $request->user(), $before, $status);

        return response()->json([
            'message' => $before['active'] ? 'All maintenance scopes are disabled. Planora is available again.' : 'No maintenance scopes were active.',
            'data' => $status,
        ]);
    }

    private function notifyIfChanged(NotificationService $notifications, \App\Models\User $actor, array $before, array $after): void
    {
        if ($before !== $after) {
            try {
                $notifications->maintenanceChanged($actor, $before, $after);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
