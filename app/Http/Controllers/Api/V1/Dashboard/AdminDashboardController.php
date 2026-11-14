<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\Dashboard\DashboardDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function show(Request $request, DashboardDataService $dashboard): JsonResponse
    {
        $viewer = $request->user();
        $canViewDashboard = collect([
            'users.view', 'activity.view', 'categories.view', 'projects.view', 'tasks.view', 'reports.view',
        ])->contains(fn (string $permission): bool => $viewer->hasManagementPermission($permission));
        abort_unless($viewer->role === 'admin' || $canViewDashboard, 403);

        return response()->json(['data' => $dashboard->for($viewer)]);
    }
}
