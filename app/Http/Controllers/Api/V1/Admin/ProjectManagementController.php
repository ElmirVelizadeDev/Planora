<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Projects\IndexProjectRequest;
use App\Http\Requests\Api\V1\Projects\StoreProjectRequest;
use App\Http\Requests\Api\V1\Projects\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\Projects\ProjectService;
use Illuminate\Http\JsonResponse;

class ProjectManagementController extends Controller
{
    public function index(IndexProjectRequest $request, ProjectService $projects): JsonResponse
    {
        $paginator = $projects->paginate($request->validated());
        return response()->json([
            'data' => ProjectResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'statistics' => $projects->statistics()],
        ]);
    }

    public function owners(): JsonResponse
    {
        $users = User::query()->where('role', 'user')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
        return response()->json(['data' => $users]);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load(['owner:id,name,email,profile_photo', 'category'])->loadCount('tasks');

        return response()->json(['project' => ProjectResource::make($project)->resolve(request())]);
    }

    public function store(StoreProjectRequest $request, ProjectService $projects): JsonResponse
    {
        return response()->json(['project' => ProjectResource::make($projects->create($request->validated()))->resolve($request)], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project, ProjectService $projects): JsonResponse
    {
        return response()->json(['project' => ProjectResource::make($projects->update($project, $request->validated()))->resolve($request)]);
    }

    public function destroy(Project $project, ProjectService $projects): JsonResponse
    {
        $projects->delete($project);
        return response()->json(['message' => 'Project deleted.']);
    }
}
