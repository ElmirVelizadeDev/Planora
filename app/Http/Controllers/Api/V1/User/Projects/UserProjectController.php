<?php

namespace App\Http\Controllers\Api\V1\User\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Projects\IndexUserProjectRequest;
use App\Http\Requests\Api\V1\User\Projects\IndexUserProjectTaskRequest;
use App\Http\Resources\Api\V1\Shared\Projects\ProjectResource;
use App\Http\Resources\Api\V1\User\Tasks\UserTaskResource;
use App\Models\Project;
use App\Services\Api\V1\Projects\UserProjectService;
use App\Services\Api\V1\Tasks\UserTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProjectController extends Controller
{
    public function index(IndexUserProjectRequest $request, UserProjectService $projects): JsonResponse
    {
        $paginator = $projects->paginateFor((int) $request->user()->id, $request->validated());
        return response()->json([
            'data' => ProjectResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function show(Request $request, int $project, UserProjectService $projects): JsonResponse
    {
        return response()->json(['project' => ProjectResource::make($projects->findFor((int) $request->user()->id, $project))->resolve($request)]);
    }

    public function tasks(IndexUserProjectTaskRequest $request, int $project, UserProjectService $projects, UserTaskService $tasks): JsonResponse
    {
        $projects->findFor((int) $request->user()->id, $project);
        $paginator = $tasks->paginateForProject((int) $request->user()->id, $project, $request->validated());
        return response()->json([
            'data' => UserTaskResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }
}
