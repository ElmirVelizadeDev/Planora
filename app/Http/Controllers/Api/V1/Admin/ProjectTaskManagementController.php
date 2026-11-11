<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Projects\StoreProjectTaskRequest;
use App\Http\Requests\Api\V1\Projects\IndexProjectTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Project;
use App\Services\Api\V1\Tasks\TaskService;
use Illuminate\Http\JsonResponse;

class ProjectTaskManagementController extends Controller
{
    public function index(IndexProjectTaskRequest $request, Project $project, TaskService $tasks): JsonResponse
    {
        $filters = $request->validated() + ['project_id' => $project->id];
        $paginator = $tasks->paginateForProject((int) $project->id, $filters);
        return response()->json([
            'data' => TaskResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function store(StoreProjectTaskRequest $request, Project $project, TaskService $tasks): JsonResponse
    {
        $task = $tasks->create($request->validated() + ['project_id' => $project->id], (int) $request->user()->id);
        return response()->json(['task' => TaskResource::make($task)->resolve($request)], 201);
    }
}
