<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class UserRolePermissionsController extends Controller
{
    public function index(): JsonResponse
    {
        $definitions = config('user_management.moderator_permissions');

        return response()->json([
            'roles' => config('user_management.roles'),
            'permissions' => $definitions,
            'matrix' => [
                'user' => [],
                'moderator' => 'assigned_per_account',
                'admin' => array_keys($definitions),
            ],
        ]);
    }
}
