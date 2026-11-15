<?php

namespace App\Http\Controllers\Api\V1\User\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\Profile\CurrentUserOverviewResource;
use App\Services\Api\V1\Projects\UserProjectService;
use App\Services\Api\V1\Tasks\UserTaskService;
use App\Http\Resources\Api\V1\Shared\Users\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(UserResource::make($request->user())->resolve());
    }

    public function overview(Request $request, UserProjectService $projects, UserTaskService $tasks): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json([
            'data' => CurrentUserOverviewResource::make([
                'projects' => $projects->statisticsFor($userId),
                'tasks' => $tasks->statistics($userId),
            ])->resolve($request),
        ]);
    }
}
